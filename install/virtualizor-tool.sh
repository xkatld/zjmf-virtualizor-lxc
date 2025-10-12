#!/bin/bash

set -e

TOOL_URL="https://github.com/xkatld/zjmf-virtualizor-lxc/raw/refs/heads/main/virtualizor-tool/virtualizor-tool"
INSTALL_PATH="/usr/local/bin/virtualizor-tool"

[ "$(uname -m)" != "x86_64" ] && echo "错误: 仅支持 x86_64 架构" && exit 1
[ "$EUID" -ne 0 ] && echo "错误: 需要 root 权限" && exit 1

echo "下载 virtualizor-tool..."
if command -v wget &> /dev/null; then
    wget -q -O "$INSTALL_PATH" "$TOOL_URL"
elif command -v curl &> /dev/null; then
    curl -sL -o "$INSTALL_PATH" "$TOOL_URL"
else
    echo "错误: 需要 wget 或 curl"
    exit 1
fi

chmod +x "$INSTALL_PATH"

if [ -f "$INSTALL_PATH" ] && [ -x "$INSTALL_PATH" ]; then
    echo "安装成功: $INSTALL_PATH"
    echo ""
    echo "使用方法:"
    echo "  virtualizor-tool license deploy                  # 部署伪授权服务"
    echo "  virtualizor-tool license status                  # 查看服务状态"
    echo "  virtualizor-tool license uninstall               # 卸载服务"
else
    echo "安装失败"
    exit 1
fi

