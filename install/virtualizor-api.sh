#!/bin/bash

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; NC='\033[0m'

REPO="https://github.com/xkatld/zjmf-virtualizor-lxc"
VERSION=""
NAME="virtualizor-api"
DIR="/opt/$NAME"
CFG="$DIR/config.yaml"
SERVICE="/etc/systemd/system/$NAME.service"
DOWNLOAD_URL=""
FORCE=false
DELETE=false

log() { echo -e "$1"; }
ok() { log "${GREEN}[OK]${NC} $1"; }
info() { log "${BLUE}[INFO]${NC} $1"; }
warn() { log "${YELLOW}[WARN]${NC} $1"; }
err() { log "${RED}[ERR]${NC} $1"; exit 1; }
escape_sed() { printf '%s' "$1" | sed -e 's/[\\/&]/\\&/g'; }

[[ $EUID -ne 0 ]] && err "请使用 root 运行"

show_help() {
  cat <<EOF
魔方财务-Virtualizor LXC 对接服务器安装脚本

使用方法:
  $0 -v <版本号> [选项]

选项:
  -v, --version <版本号>  指定安装版本 (必需)
  -f, --force             强制重新安装
  -d, --delete            删除服务和数据
  -h, --help              显示此帮助信息

示例:
  $0 -v 1.0.0             安装 v1.0.0 版本
  $0 -v v1.0.0 -f         强制安装 v1.0.0
  $0 -d                   删除服务

EOF
  exit 0
}

while [[ $# -gt 0 ]]; do
  case $1 in
    -v|--version) VERSION="$2"; [[ $VERSION != v* ]] && VERSION="v$VERSION"; shift 2;;
    -f|--force) FORCE=true; shift;;
    -d|--delete) DELETE=true; shift;;
    -h|--help) show_help;;
    *) err "未知参数: $1 (使用 -h 查看帮助)";;
  esac
done

if [[ $DELETE == true ]]; then
  echo "警告: 此操作将删除所有数据和配置！"
  echo
  read -p "确定要继续吗? (y/N): " CONFIRM
  if [[ $CONFIRM != "y" && $CONFIRM != "Y" ]]; then
    ok "取消删除操作"
    exit 0
  fi
  
  systemctl stop $NAME 2>/dev/null || true
  systemctl disable $NAME 2>/dev/null || true
  rm -f "$SERVICE"
  systemctl daemon-reload
  if [[ -d "$DIR" ]]; then
    rm -rf "$DIR"
    ok "已删除 $NAME 服务和目录"
  else
    ok "目录 $DIR 不存在，无需删除"
  fi
  exit 0
fi

if [[ -z "$VERSION" ]]; then
  err "必须提供版本号参数 (使用 -v 或 --version)，使用 -h 查看帮助"
fi

echo
echo "========================================"
echo "      步骤 1/6: 检测系统环境"
echo "========================================"
echo

info "检测操作系统..."
if [[ -f /etc/os-release ]]; then
  OS_NAME=$(grep '^NAME=' /etc/os-release | cut -d'"' -f2)
  OS_VERSION=$(grep '^VERSION_ID=' /etc/os-release | cut -d'"' -f2)
  echo "  系统: $OS_NAME ${OS_VERSION:-}"
else
  echo "  系统: 未知 Linux 发行版"
fi

info "检测系统架构..."
arch=$(uname -m)
case $arch in
  x86_64) 
    BIN="virtualizor-api"
    echo "  架构: amd64"
    ;;
  *) 
    err "不支持的架构: $arch，仅支持 x86_64 (amd64)"
    ;;
esac

ok "系统环境检测完成"

echo
echo "========================================"
echo "      步骤 2/6: 检查 Virtualizor 环境"
echo "========================================"
echo

info "检查 Virtualizor 是否已安装..."
if [[ ! -d "/usr/local/virtualizor" ]]; then
  warn "未检测到 Virtualizor 安装目录"
  echo "  提示: Virtualizor 通常安装在 /usr/local/virtualizor"
else
  ok "Virtualizor 已安装"
fi

if command -v vzctl &> /dev/null; then
  info "检测到 vzctl 命令"
elif command -v virtualizor &> /dev/null; then
  info "检测到 virtualizor 命令"
else
  warn "未检测到 Virtualizor 命令行工具"
fi

ok "Virtualizor 环境检查完成"

echo
echo "========================================"
echo "      步骤 3/6: 检查版本状态"
echo "========================================"
echo

DOWNLOAD_URL="$REPO/releases/download/$VERSION/$NAME.zip"

UPGRADE=false
if [[ -d "$DIR" ]] && [[ -f "$DIR/version" ]]; then
  CUR=$(cat "$DIR/version")
  if [[ $CUR != "$VERSION" || $FORCE == true ]]; then
    UPGRADE=true
    info "检测到已安装版本: $CUR"
    info "执行升级操作: $CUR -> $VERSION"
  else
    ok "已是最新版本 $VERSION"
    exit 0
  fi
else
  info "未检测到已安装版本"
  info "执行全新安装: $VERSION"
fi

echo
echo "========================================"
echo "      步骤 4/6: 安装系统依赖"
echo "========================================"
echo

info "检测包管理器..."
pkg_manager=""
if command -v dnf &> /dev/null; then
  pkg_manager="dnf"
  echo "  使用: DNF (RHEL 8+/Fedora)"
elif command -v yum &> /dev/null; then
  pkg_manager="yum"
  echo "  使用: YUM (RHEL/CentOS)"
else
  err "不支持的系统，Virtualizor 仅支持 RHEL/CentOS/AlmaLinux"
fi

info "安装依赖包..."
case $pkg_manager in
  dnf)
    dnf install -y curl wget openssl systemd unzip || err "依赖安装失败"
    ;;
  yum)
    yum install -y curl wget openssl systemd unzip || err "依赖安装失败"
    ;;
esac

ok "系统依赖安装完成"

echo
echo "========================================"
echo "      步骤 5/6: 准备环境和下载程序"
echo "========================================"
echo

if systemctl is-active --quiet $NAME 2>/dev/null; then
  info "停止当前服务..."
  systemctl stop $NAME 2>/dev/null || true
  ok "服务已停止"
else
  info "服务未运行，跳过停止操作"
fi

if [[ $UPGRADE == true ]]; then
  info "备份配置文件..."
  if [[ -f "$CFG" ]]; then
    cp "$CFG" "$CFG.bak"
    ok "配置文件已备份: $CFG.bak"
  fi
  
  info "清理旧程序文件..."
  find "$DIR" -maxdepth 1 -type f ! -name "*.bak" ! -name "*.db" -delete 2>/dev/null || true
  for subdir in "$DIR"/*; do
    if [[ -d "$subdir" ]] && [[ ! "$subdir" =~ (logs|configs)$ ]]; then
      rm -rf "$subdir" 2>/dev/null || true
    fi
  done
  ok "旧文件已清理（保留配置和日志）"
fi

info "创建安装目录..."
mkdir -p "$DIR"
mkdir -p "$DIR/logs"
mkdir -p "$DIR/configs"
ok "目录结构已创建"

info "下载 $NAME $VERSION..."
TMP=$(mktemp -d)
wget -qO "$TMP/$NAME.zip" "$DOWNLOAD_URL" || err "下载失败: $DOWNLOAD_URL"

info "解压安装文件..."
unzip -qo "$TMP/$NAME.zip" -d "$TMP" || err "解压失败"
rm -f "$TMP/$NAME.zip"
chmod +x "$TMP/$BIN" 2>/dev/null || true
cp -a "$TMP"/. "$DIR/"
chmod +x "$DIR/$BIN"
echo "$VERSION" > "$DIR/version"

ok "程序文件安装完成"

if [[ -f "$CFG.bak" ]]; then
  echo
  info "检测到旧配置文件"
  read -p "是否使用旧配置? (Y/n): " USE_OLD
  if [[ $USE_OLD != "n" && $USE_OLD != "N" ]]; then
    mv "$CFG.bak" "$CFG"
    rm -rf "$TMP"
    ok "已恢复旧配置"
    
    info "创建系统服务..."
    cat > "$SERVICE" <<EOF
[Unit]
Description=Virtualizor API Service
After=network.target

[Service]
WorkingDirectory=$DIR
ExecStart=$DIR/$BIN
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
    
    info "启动服务..."
    systemctl daemon-reload
    systemctl enable --now $NAME
    
    echo
    echo "========================================"
    ok "升级完成"
    echo "========================================"
    echo
    
    info "等待服务启动..."
    sleep 3
    
    echo
    echo "========================================"
    echo "服务状态"
    echo "========================================"
    echo
    systemctl status $NAME --no-pager --lines=10
    
    echo
    exit 0
  fi
fi

if [[ ! -f "$DIR/config.yaml" ]]; then
  rm -rf "$TMP"
  err "压缩包中未找到配置模板 config.yaml"
fi

rm -rf "$TMP"

echo
echo "========================================"
echo "      步骤 6/6: 配置向导"
echo "========================================"
echo
echo "    Virtualizor API 服务配置向导 - $VERSION"
echo
echo "提示: 共 7 个配置步骤，包括基础配置、Virtualizor、NAT、IPv6、反向代理等"
echo

DEFAULT_IP=$(curl -s 4.ipw.cn 2>/dev/null || hostname -I | awk '{print $1}' || echo "")
DEFAULT_INTERFACE=$(ip route | grep default | head -1 | awk '{print $5}' || echo "eth0")
DEFAULT_API_KEY=$(openssl rand -hex 16 | tr 'a-f' 'A-F')

echo "==== 步骤 1/7: 基础配置 ===="
echo

read -p "API 服务端口 [8080]: " SERVER_PORT
SERVER_PORT=${SERVER_PORT:-8080}

read -p "API 访问密钥 [$DEFAULT_API_KEY]: " API_KEY
API_KEY=${API_KEY:-$DEFAULT_API_KEY}

ok "基础配置完成"
echo

echo "==== 步骤 2/7: Virtualizor 配置 ===="
echo

while [[ -z "$VZ_API_KEY" ]]; do
  read -p "Virtualizor API Key: " VZ_API_KEY
  [[ -z "$VZ_API_KEY" ]] && warn "API Key 不能为空"
done

while [[ -z "$VZ_API_PASSWORD" ]]; do
  read -p "Virtualizor API Password: " VZ_API_PASSWORD
  [[ -z "$VZ_API_PASSWORD" ]] && warn "API Password 不能为空"
done

ok "Virtualizor 配置完成"
echo

echo "==== 步骤 3/7: NAT 网络配置 ===="
echo

read -p "NAT 公网 IP [$DEFAULT_IP]: " NAT_PUBLIC_IP
NAT_PUBLIC_IP=${NAT_PUBLIC_IP:-$DEFAULT_IP}

while [[ -z "$NAT_PUBLIC_IP" ]]; do
  warn "NAT 公网 IP 不能为空"
  read -p "NAT 公网 IP: " NAT_PUBLIC_IP
done

read -p "NAT 网卡接口 [$DEFAULT_INTERFACE]: " NAT_INTERFACE
NAT_INTERFACE=${NAT_INTERFACE:-$DEFAULT_INTERFACE}

ok "NAT 网络配置完成"
echo

echo "==== 步骤 4/7: IPv6 独立绑定配置 ===="
echo
echo "提示: IPv6 独立绑定允许为容器分配独立的公网 IPv6 地址"
echo

read -p "是否启用 IPv6 独立绑定功能? (y/N): " IPV6_ENABLE
if [[ $IPV6_ENABLE == "y" || $IPV6_ENABLE == "Y" ]]; then
  IPV6_ENABLED="true"
  
  read -p "IPv6 网卡接口 [$DEFAULT_INTERFACE]: " IPV6_INTERFACE
  IPV6_INTERFACE=${IPV6_INTERFACE:-$DEFAULT_INTERFACE}
  
  read -p "IPv6 地址池起始地址 [2001:db8::1000]: " IPV6_START
  IPV6_START=${IPV6_START:-"2001:db8::1000"}
  
  IPV6_PREFIX="64"
  IPV6_POOL_SIZE="1000"
  
  ok "IPv6 独立绑定功能已启用"
else
  IPV6_ENABLED="false"
  IPV6_INTERFACE="eth0"
  IPV6_START="2001:db8::1000"
  IPV6_PREFIX="64"
  IPV6_POOL_SIZE="1000"
  info "IPv6 独立绑定功能已禁用"
fi
echo

echo "==== 步骤 5/7: Nginx 反向代理配置 ===="
echo
echo "提示: Nginx 反向代理允许为容器绑定域名，支持 SSL/TLS"
echo

read -p "是否启用 Nginx 反向代理功能? (y/N): " PROXY_ENABLE
if [[ $PROXY_ENABLE == "y" || $PROXY_ENABLE == "Y" ]]; then
  PROXY_ENABLED="true"
  
  info "检查 Nginx 是否已安装..."
  if ! command -v nginx &> /dev/null; then
    info "自动安装 Nginx..."
    case $pkg_manager in
      dnf)
        dnf install -y nginx || warn "Nginx 安装失败，请手动安装"
        ;;
      yum)
        yum install -y nginx || warn "Nginx 安装失败，请手动安装"
        ;;
    esac
  fi
  
  if command -v nginx &> /dev/null; then
    ok "Nginx 已安装"
    
    info "启动并启用 Nginx..."
    systemctl enable --now nginx 2>/dev/null || true
    
    if [[ ! -d /etc/nginx/conf.d ]]; then
      mkdir -p /etc/nginx/conf.d
      ok "创建 Nginx 配置目录"
    fi
  else
    warn "Nginx 未安装，反向代理功能可能无法正常工作"
  fi
  
  ok "Nginx 反向代理功能已启用"
else
  PROXY_ENABLED="false"
  info "Nginx 反向代理功能已禁用"
fi
echo

info "正在生成配置文件..."

sed -i "s|SERVER_PORT|$SERVER_PORT|g" "$CFG"
sed -i "s|\"API_KEY\"|\"$(escape_sed "$API_KEY")\"|g" "$CFG"
sed -i "s|\"VZ_API_KEY\"|\"$(escape_sed "$VZ_API_KEY")\"|g" "$CFG"
sed -i "s|\"VZ_API_PASSWORD\"|\"$(escape_sed "$VZ_API_PASSWORD")\"|g" "$CFG"
sed -i "s|\"NAT_PUBLIC_IP\"|\"$(escape_sed "$NAT_PUBLIC_IP")\"|g" "$CFG"
sed -i "s|\"NAT_INTERFACE\"|\"$(escape_sed "$NAT_INTERFACE")\"|g" "$CFG"

sed -i "s|IPV6_ENABLED|$IPV6_ENABLED|g" "$CFG"
sed -i "s|\"IPV6_INTERFACE\"|\"$(escape_sed "$IPV6_INTERFACE")\"|g" "$CFG"
sed -i "s|\"IPV6_START\"|\"$(escape_sed "$IPV6_START")\"|g" "$CFG"
sed -i "s|IPV6_PREFIX|$IPV6_PREFIX|g" "$CFG"
sed -i "s|IPV6_POOL_SIZE|$IPV6_POOL_SIZE|g" "$CFG"

sed -i "s|PROXY_ENABLED|$PROXY_ENABLED|g" "$CFG"

ok "配置文件已生成"

info "创建系统服务..."

cat > "$SERVICE" <<EOF
[Unit]
Description=Virtualizor API Service - xkatld
After=network.target

[Service]
WorkingDirectory=$DIR
ExecStart=$DIR/$BIN
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

info "启动服务..."
systemctl daemon-reload
systemctl enable --now $NAME

ok "系统服务已创建并启动"

echo
echo "========================================"
ok "安装完成"
echo "========================================"
echo
echo "服务端口: $SERVER_PORT"
echo "API 密钥: $API_KEY"
echo "数据目录: $DIR"
echo
echo "========================================"
echo "魔方财务配置参数"
echo "========================================"
echo
echo "服务器类型: virtualizor"
echo "服务器地址: $NAT_PUBLIC_IP"
echo "服务器端口: $SERVER_PORT"
echo "访问哈希值: $API_KEY"
echo

info "等待服务启动..."
sleep 3

echo
info "服务状态:"
systemctl status $NAME --no-pager --lines=10

echo
if systemctl is-active --quiet $NAME 2>/dev/null; then
  ok "服务运行正常"
else
  warn "服务状态异常，请检查日志"
fi
echo