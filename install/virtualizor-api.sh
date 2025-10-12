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

[[ $EUID -ne 0 ]] && err "请使用 root 运行"

while [[ $# -gt 0 ]]; do
  case $1 in
    -v|--version) VERSION="$2"; [[ $VERSION != v* ]] && VERSION="v$VERSION"; shift 2;;
    -f|--force) FORCE=true; shift;;
    -d|--delete) DELETE=true; shift;;
    -h|--help) echo "$0 -v 版本 [-f] [-d]"; exit 0;;
    *) err "未知参数 $1";;
  esac
done

if [[ $DELETE == true ]]; then
  echo "警告: 此操作将删除所有数据和配置！"
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
    ok "已强制删除 $NAME 服务和目录"
  else
    ok "目录 $DIR 不存在，无需删除"
  fi
  exit 0
fi

if [[ -z "$VERSION" ]]; then
  err "必须提供版本号参数，使用 -v 或 --version 指定版本"
fi

arch=$(uname -m)
case $arch in
  x86_64) BIN="virtualizor-api";;
  *) err "不支持的架构: $arch，仅支持 x86_64 (AMD64)";;
esac

DOWNLOAD_URL="$REPO/releases/download/$VERSION/$NAME.zip"

UPGRADE=false
if [[ -d "$DIR" ]] && [[ -f "$DIR/version" ]]; then
  CUR=$(cat "$DIR/version")
  if [[ $CUR != "$VERSION" || $FORCE == true ]]; then
    UPGRADE=true
    info "升级: $CUR -> $VERSION"
  else
    ok "已是最新版本 $VERSION"
    exit 0
  fi
fi

if command -v dnf &> /dev/null; then
  dnf install -y curl wget openssl systemd unzip
elif command -v yum &> /dev/null; then
  yum install -y curl wget openssl systemd unzip
else
  err "不支持的系统，Virtualizor 仅支持 RHEL/CentOS/Fedora"
fi

systemctl stop $NAME 2>/dev/null || true

if [[ $UPGRADE == true ]]; then
  if [[ -f "$CFG" ]]; then
    cp "$CFG" "$CFG.bak"
    ok "配置文件已备份: $CFG.bak"
  fi
  
  find "$DIR" -maxdepth 1 -type f ! -name "*.bak" -delete
  find "$DIR" -maxdepth 1 -type d ! -name "logs" ! -name "configs" -exec rm -rf {} + 2>/dev/null || true
fi

mkdir -p "$DIR"
mkdir -p "$DIR/logs"
mkdir -p "$DIR/configs"

TMP=$(mktemp -d)
wget -qO "$TMP/$NAME.zip" "$DOWNLOAD_URL" || err "下载失败"
unzip -q "$TMP/$NAME.zip" -d "$TMP" || err "解压失败"
chmod +x "$TMP/$BIN"
mv "$TMP/$BIN" "$DIR/"
echo "$VERSION" > "$DIR/version"
rm -rf "$TMP"

if [[ -f "$CFG.bak" ]]; then
  read -p "检测到旧配置，是否使用? (Y/n): " USE_OLD
  if [[ $USE_OLD != "n" && $USE_OLD != "N" ]]; then
    mv "$CFG.bak" "$CFG"
    ok "已恢复旧配置"
    
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
    
    systemctl daemon-reload
    systemctl enable --now $NAME
    ok "升级完成"
    systemctl status $NAME --no-pager
    exit 0
  fi
fi

CONFIG_URL="$REPO/raw/main/install/config.yaml"
info "下载配置模板..."
wget -qO "$CFG" "$CONFIG_URL" || err "配置模板下载失败"

read -p "API 服务端口 [8080]: " SERVER_PORT
SERVER_PORT=${SERVER_PORT:-8080}

while [[ -z "$API_KEY" ]]; do
  read -p "API 访问密钥: " API_KEY
  [[ -z "$API_KEY" ]] && warn "不能为空"
done

while [[ -z "$VZ_API_KEY" ]]; do
  read -p "Virtualizor API Key: " VZ_API_KEY
  [[ -z "$VZ_API_KEY" ]] && warn "不能为空"
done

while [[ -z "$VZ_API_PASSWORD" ]]; do
  read -p "Virtualizor API Password: " VZ_API_PASSWORD
  [[ -z "$VZ_API_PASSWORD" ]] && warn "不能为空"
done

sed -i "s/SERVER_PORT/$SERVER_PORT/" "$CFG"
sed -i "s/API_KEY/$API_KEY/" "$CFG"
sed -i "s/VZ_API_KEY/$VZ_API_KEY/" "$CFG"
sed -i "s/VZ_API_PASSWORD/$VZ_API_PASSWORD/" "$CFG"

ok "配置文件已生成"

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

systemctl daemon-reload
systemctl enable --now $NAME

ok "安装完成"
echo
echo "数据目录: $DIR"
echo "API 端口: $SERVER_PORT"
echo "API 密钥: $API_KEY"
echo
echo "魔方财务配置:"
echo "  服务器类型: virtualizor"
echo "  IP: $(curl -s 4.ipw.cn 2>/dev/null || hostname -I | awk '{print $1}')"
echo "  端口: $SERVER_PORT"
echo "  访问哈希: $API_KEY"
echo
systemctl status $NAME --no-pager

