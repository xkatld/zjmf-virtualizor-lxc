#!/bin/bash

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; NC='\033[0m'

log() { echo -e "$1"; }
ok() { log "${GREEN}[OK]${NC} $1"; }
info() { log "${BLUE}[INFO]${NC} $1"; }
warn() { log "${YELLOW}[WARN]${NC} $1"; }
err() { log "${RED}[ERR]${NC} $1"; exit 1; }

[[ $EUID -ne 0 ]] && err "请使用 root 运行"

show_help() {
  cat <<EOF
Virtualizor 安装脚本

使用方法:
  $0 [选项]

选项:
  -h, --help              显示此帮助信息

说明:
  本脚本支持 AlmaLinux 8.x / 9.x (x86_64) 系统
  将自动安装和配置 Virtualizor 面板及其依赖

示例:
  $0                      开始安装

EOF
  exit 0
}

while [[ $# -gt 0 ]]; do
  case $1 in
    -h|--help) show_help;;
    *) err "未知参数: $1 (使用 -h 查看帮助)";;
  esac
done

echo
echo "========================================"
echo "      Virtualizor 安装脚本"
echo "========================================"
echo

echo
echo "========================================"
echo "      步骤 1/5: 检查系统和架构"
echo "========================================"
echo

info "检测操作系统..."
if [[ ! -f /etc/os-release ]]; then
  err "无法检测操作系统版本"
fi

OS_NAME=$(grep '^NAME=' /etc/os-release | cut -d'"' -f2)
OS_ID=$(grep '^ID=' /etc/os-release | cut -d'"' -f2)
OS_VERSION=$(grep '^VERSION_ID=' /etc/os-release | cut -d'"' -f2)

echo "  系统: $OS_NAME $OS_VERSION"
echo "  ID: $OS_ID"

if [[ "$OS_ID" != "almalinux" ]]; then
  err "不支持的操作系统: $OS_NAME，仅支持 AlmaLinux"
fi

if [[ "$OS_VERSION" != "8"* && "$OS_VERSION" != "9"* ]]; then
  err "不支持的版本: $OS_VERSION，仅支持 AlmaLinux 8.x 和 9.x"
fi

ok "操作系统检查通过"

info "检测系统架构..."
arch=$(uname -m)
case $arch in
  x86_64) 
    echo "  架构: amd64"
    ;;
  *) 
    err "不支持的架构: $arch，仅支持 x86_64 (amd64)"
    ;;
esac

ok "系统架构检查通过"

info "检查系统内存..."
total_mem=$(free -m | awk '/^Mem:/{print $2}')
echo "  总内存: ${total_mem}MB"
if [[ $total_mem -lt 1024 ]]; then
  warn "内存小于 1GB，可能影响 Virtualizor 性能"
fi

info "检查磁盘空间..."
root_space=$(df -BG / | awk 'NR==2 {print $4}' | sed 's/G//')
echo "  可用空间: ${root_space}GB"
if [[ $root_space -lt 20 ]]; then
  warn "可用空间小于 20GB，建议扩容后安装"
fi

ok "系统环境检查完成"

echo
echo "========================================"
echo "      步骤 2/5: 安装基础依赖包"
echo "========================================"
echo

info "安装必需依赖..."
dnf install -y wget lvm2 || err "依赖安装失败"

ok "依赖安装完成"

echo
echo "========================================"
echo "      步骤 3/5: 配置 LVM 存储池"
echo "========================================"
echo

info "当前 LVM 配置:"
if vgs &>/dev/null; then
  vgs 2>/dev/null
else
  echo "  无卷组"
fi
echo

echo "请选择 LVM 配置方式:"
echo "  1. 使用已有卷组"
echo "  2. 创建新卷组 (loop 设备)"
echo

read -p "请选择 [1-2]: " LVM_CHOICE

VG_NAME=""

case $LVM_CHOICE in
  1)
    echo
    info "使用已有卷组"
    while [[ -z "$VG_NAME" ]]; do
      read -p "请输入已有卷组名称: " VG_NAME
      if [[ -n "$VG_NAME" ]]; then
        if ! vgs "$VG_NAME" &>/dev/null; then
          warn "卷组 $VG_NAME 不存在"
          VG_NAME=""
        else
          ok "卷组 $VG_NAME 已确认"
        fi
      fi
    done
    ;;
    
  2)
    echo
    info "创建新卷组 (loop 设备)"
    echo
    warn "注意: 此方式将在根目录创建镜像文件，占用系统盘空间"
    warn "      如需使用独立硬盘，请先创建卷组后选择方式 1"
    echo
    
    read -p "请输入磁盘镜像大小 (GB) [20]: " DISK_SIZE_GB
    DISK_SIZE_GB=${DISK_SIZE_GB:-20}
    DISK_SIZE=$((DISK_SIZE_GB * 1024))
    
    read -p "请输入卷组名称 [vg0]: " VG_NAME
    VG_NAME=${VG_NAME:-vg0}
    
    DISK_IMG="/lxcdisk.img"
    LOOP_DEVICE="/dev/loop0"
    
    if [[ -f "$DISK_IMG" ]]; then
      warn "磁盘镜像 $DISK_IMG 已存在"
      read -p "是否删除并重建? (y/N): " RECREATE
      if [[ $RECREATE == "y" || $RECREATE == "Y" ]]; then
        losetup -d "$LOOP_DEVICE" 2>/dev/null || true
        rm -f "$DISK_IMG"
      else
        err "取消创建"
      fi
    fi
    
    info "创建磁盘镜像文件 (${DISK_SIZE_GB}GB)..."
    dd if=/dev/zero of="$DISK_IMG" bs=1M seek="$DISK_SIZE" count=0 || err "创建磁盘镜像失败"
    ok "磁盘镜像创建成功"
    
    info "设置 loop 设备..."
    losetup "$LOOP_DEVICE" "$DISK_IMG" || err "设置 loop 设备失败"
    ok "loop 设备设置成功"
    
    info "创建物理卷..."
    pvcreate "$LOOP_DEVICE" || err "创建物理卷失败"
    ok "物理卷创建成功"
    
    info "创建卷组: $VG_NAME"
    vgcreate -s 32M "$VG_NAME" "$LOOP_DEVICE" || err "创建卷组失败"
    ok "卷组创建成功"
    
    echo
    info "配置开机自动挂载..."
    
    cat > /etc/systemd/system/lxcdisk-loop.service <<EOF
[Unit]
Description=Setup loop device for LXC disk image
Before=lvm2-activation-early.service
DefaultDependencies=no

[Service]
Type=oneshot
ExecStart=/sbin/losetup $LOOP_DEVICE $DISK_IMG
RemainAfterExit=yes

[Install]
WantedBy=local-fs.target
EOF
    
    systemctl daemon-reload
    systemctl enable lxcdisk-loop.service || warn "启用服务失败"
    ok "开机自动挂载已配置"
    
    echo
    info "卷组信息:"
    vgs "$VG_NAME" 2>/dev/null
    echo
    ok "LVM 存储池配置完成"
    ;;
    
  *)
    err "无效的选择"
    ;;
esac

echo
echo "========================================"
echo "      步骤 4/5: 安装 Virtualizor"
echo "========================================"
echo

info "准备安装配置..."
echo

read -p "请输入邮箱地址 [xx@xxxx.com]: " EMAIL
EMAIL=${EMAIL:-xx@xxxx.com}

if [[ ! "$EMAIL" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
  err "邮箱格式不正确"
fi

ok "邮箱: $EMAIL"
ok "卷组: $VG_NAME"
ok "内核: lxc"
echo

info "下载 Virtualizor 安装脚本..."
wget -N http://files.virtualizor.com/install.sh || err "下载安装脚本失败"
ok "安装脚本下载完成"

info "设置执行权限..."
chmod 0755 install.sh || err "设置权限失败"

echo
warn "即将开始安装 Virtualizor，此过程可能需要 10-30 分钟"
warn "安装过程中请勿中断，等待安装完成"
echo

read -p "按 Enter 键继续安装，或 Ctrl+C 取消..."

echo
info "开始安装 Virtualizor..."
info "安装日志: /root/virtualizor.log"
echo "========================================"
echo

LOG_FILE="/root/virtualizor.log"
if [[ -f "$LOG_FILE" ]]; then
  mv "$LOG_FILE" "$LOG_FILE.old"
fi

./install.sh email="$EMAIL" kernel=lxc lvg="$VG_NAME" noos=true
INSTALL_EXIT=$?

if [[ $INSTALL_EXIT -ne 0 ]]; then
  echo
  err "Virtualizor 安装失败，退出码: $INSTALL_EXIT"
fi

rm -f install.sh

echo
echo "========================================"
ok "Virtualizor 安装完成"

echo
echo "========================================"
echo "      步骤 5/5: 配置网络和检查"
echo "========================================"
echo

info "关闭默认桥接网卡..."
systemctl stop virtnetwork 2>/dev/null || true
systemctl disable virtnetwork 2>/dev/null || true
ok "已关闭默认桥接网卡"

echo
read -p "是否配置 NAT 桥接网卡? (y/N): " SETUP_BRIDGE

if [[ $SETUP_BRIDGE == "y" || $SETUP_BRIDGE == "Y" ]]; then
  echo
  info "安装 iptables 持久化软件..."
  dnf install -y iptables iptables-services || err "iptables 安装失败"
  systemctl enable iptables ip6tables 2>/dev/null || true
  ok "iptables 已安装"
  
  echo
  info "配置 NAT 桥接网卡..."
  echo
  
  DEFAULT_NIC=$(ip route | grep default | head -1 | awk '{print $5}' || echo "eth0")
  read -p "外网网卡名称 [$DEFAULT_NIC]: " EXT_NIC
  EXT_NIC=${EXT_NIC:-$DEFAULT_NIC}
  
  read -p "IPv4 内网地址 [10.0.2.1/24]: " IPV4_ADDR
  IPV4_ADDR=${IPV4_ADDR:-10.0.2.1/24}
  IPV4_SUBNET=$(echo $IPV4_ADDR | cut -d'/' -f1 | cut -d'.' -f1-3).0/24
  
  read -p "IPv6 内网地址 [fd00:10:2::1/64]: " IPV6_ADDR
  IPV6_ADDR=${IPV6_ADDR:-fd00:10:2::1/64}
  IPV6_SUBNET=$(echo $IPV6_ADDR | cut -d'/' -f1 | sed 's/::[^:]*$/::\/64/')
  
  echo
  info "创建桥接网卡 netbr0..."
  nmcli connection add type bridge ifname netbr0 stp no || err "创建桥失败"
  ok "桥接网卡已创建"
  
  info "配置 IP 地址..."
  nmcli connection modify "bridge-netbr0" ipv4.method manual ipv4.addresses $IPV4_ADDR ipv4.dns 8.8.8.8 || warn "IPv4 配置失败"
  nmcli connection modify "bridge-netbr0" ipv6.method manual ipv6.addresses $IPV6_ADDR ipv6.dns 2001:4860:4860::8888 || warn "IPv6 配置失败"
  ok "IP 地址已配置"
  
  info "激活桥接网卡..."
  nmcli connection reload
  nmcli connection down "bridge-netbr0" 2>/dev/null || true
  nmcli connection up "bridge-netbr0" || err "激活桥失败"
  ok "桥接网卡已激活"
  
  info "配置 dummy0 接口..."
  modprobe dummy || warn "加载 dummy 模块失败"
  ip link add dummy0 type dummy 2>/dev/null || warn "dummy0 可能已存在"
  ip link set dummy0 up
  ip link set dummy0 master netbr0
  ok "dummy0 已配置"
  
  info "启用 IP 转发..."
  cat > /etc/sysctl.d/99-ip-forward.conf <<EOF
net.ipv4.ip_forward=1
net.ipv6.conf.all.forwarding=1
EOF
  sysctl -p /etc/sysctl.d/99-ip-forward.conf
  ok "IP 转发已启用"
  
  info "配置 NAT 规则..."
  iptables -t nat -A POSTROUTING -s $IPV4_SUBNET -o $EXT_NIC -j MASQUERADE || warn "IPv4 NAT 配置失败"
  ip6tables -t nat -A POSTROUTING -s $IPV6_SUBNET -o $EXT_NIC -j MASQUERADE || warn "IPv6 NAT 配置失败"
  
  mkdir -p /etc/sysconfig
  iptables-save > /etc/sysconfig/iptables || warn "保存 iptables 规则失败"
  ip6tables-save > /etc/sysconfig/ip6tables || warn "保存 ip6tables 规则失败"
  ok "NAT 规则已配置"
  
  info "配置 dummy0 持久化..."
  cat > /etc/systemd/system/dummy0-netbr0.service <<EOF
[Unit]
Description=Activate dummy0 for netbr0 bridge
After=network.target NetworkManager.service
Wants=network.target

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/bin/bash -c 'modprobe dummy; ip link add dummy0 type dummy; ip link set dummy0 up; ip link set dummy0 master netbr0'
ExecStop=/bin/bash -c 'ip link delete dummy0'

[Install]
WantedBy=multi-user.target
EOF
  
  systemctl daemon-reload
  systemctl enable dummy0-netbr0.service || warn "启用 dummy0 服务失败"
  ok "dummy0 持久化已配置"
  
  echo
  ok "NAT 桥接网卡配置完成"
  echo "  桥接网卡: netbr0"
  echo "  IPv4: $IPV4_ADDR"
  echo "  IPv6: $IPV6_ADDR"
  echo "  外网网卡: $EXT_NIC"
else
  info "跳过 NAT 桥接配置"
fi

echo

info "检查 Virtualizor 目录..."
if [[ -d "/usr/local/virtualizor" ]]; then
  ok "Virtualizor 目录存在"
  echo "  路径: /usr/local/virtualizor"
else
  err "Virtualizor 目录不存在"
fi

info "检查 Virtualizor 命令..."
if command -v virtualizor &> /dev/null; then
  ok "virtualizor 命令可用"
  virtualizor --version 2>/dev/null || echo "  版本信息不可用"
elif command -v vzctl &> /dev/null; then
  ok "vzctl 命令可用"
else
  warn "未检测到 Virtualizor 命令"
fi

info "检查 Virtualizor 服务..."
if systemctl is-active --quiet virtualizor 2>/dev/null; then
  ok "Virtualizor 服务运行中"
else
  warn "Virtualizor 服务未运行或状态未知"
fi

info "检查 Web 面板..."
if netstat -tuln 2>/dev/null | grep -q ":4083\|:4085" || ss -tuln 2>/dev/null | grep -q ":4083\|:4085"; then
  ok "Web 面板端口已监听"
else
  warn "Web 面板端口未检测到"
fi

info "获取服务器 IP 地址..."
SERVER_IP=$(curl -s 4.ipw.cn 2>/dev/null || hostname -I | awk '{print $1}' || echo "未知")

echo
echo "========================================"
ok "安装完成"
echo "========================================"
echo
echo "面板地址: https://$SERVER_IP:4085"
if [[ -n "$VG_NAME" ]] && vgs "$VG_NAME" &>/dev/null; then
  VG_SIZE=$(vgs --noheadings -o vg_size "$VG_NAME" 2>/dev/null | xargs | tr '[:lower:]' '[:upper:]')
  echo "存储卷组: $VG_NAME ($VG_SIZE)"
fi
if [[ -n "$IPV4_SUBNET" ]]; then
  echo "内网段v4: $IPV4_SUBNET"
  if [[ -n "$IPV6_SUBNET" ]]; then
    echo "内网段v6: $IPV6_SUBNET"
  fi
fi
echo
warn "重要: 请务必登录 Web 面板重新生成 API 凭证:"
echo "  1. 访问管理面板并登录"
echo "  2. 进入 Configuration -> Server Info"
echo "  3. 点击 Reset API keys 按钮"
echo

