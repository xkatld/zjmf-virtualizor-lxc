#!/bin/bash

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; NC='\033[0m'

TOOL_URL="https://github.com/xkatld/zjmf-virtualizor-lxc/raw/refs/heads/main/virtualizor-tool/virtualizor-tool"
INSTALL_PATH="/usr/local/bin/virtualizor-tool"

log() { echo -e "$1"; }
ok() { log "${GREEN}[OK]${NC} $1"; }
info() { log "${BLUE}[INFO]${NC} $1"; }
warn() { log "${YELLOW}[WARN]${NC} $1"; }
err() { log "${RED}[ERR]${NC} $1"; exit 1; }

[[ $EUID -ne 0 ]] && err "请使用 root 运行"

echo
echo "========================================"
echo "  Virtualizor Tool 安装脚本"
echo "========================================"
echo

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

ok "系统检测完成"
echo

info "下载 virtualizor-tool..."
if command -v wget &> /dev/null; then
    wget -q --show-progress -O "$INSTALL_PATH" "$TOOL_URL" || err "下载失败"
elif command -v curl &> /dev/null; then
    curl -# -L -o "$INSTALL_PATH" "$TOOL_URL" || err "下载失败"
else
    err "需要 wget 或 curl，请先安装"
fi

info "设置执行权限..."
chmod +x "$INSTALL_PATH" || err "设置权限失败"

if [[ -f "$INSTALL_PATH" ]] && [[ -x "$INSTALL_PATH" ]]; then
    ok "安装成功: $INSTALL_PATH"
else
    err "安装验证失败"
fi

echo
echo "========================================"
echo "  安装完成"
echo "========================================"
echo
echo "工具路径: $INSTALL_PATH"
echo
echo "使用方法:"
echo "  virtualizor-tool license deploy      # 部署伪授权服务"
echo "  virtualizor-tool license status      # 查看服务状态"
echo "  virtualizor-tool license uninstall   # 卸载服务"
echo "  virtualizor-tool --help              # 查看完整帮助"
echo
ok "您现在可以使用 virtualizor-tool 命令了"
echo

