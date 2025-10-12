#!/bin/bash

set -e

TOOL_URL="https://github.com/xkatld/zjmf-virtualizor-lxc/raw/refs/heads/main/virtualizor-tool/virtualizor-tool"
INSTALL_PATH="/usr/local/bin/virtualizor-tool"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Virtualizor Tool 安装脚本${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

if [ "$(uname -m)" != "x86_64" ]; then
    echo -e "${RED}错误: 仅支持 x86_64 (AMD64) 架构${NC}"
    exit 1
fi

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}错误: 请使用 root 权限运行此脚本${NC}"
    exit 1
fi

echo -e "${YELLOW}[1/4] 下载 virtualizor-tool...${NC}"
if command -v wget &> /dev/null; then
    wget -q --show-progress -O "$INSTALL_PATH" "$TOOL_URL"
elif command -v curl &> /dev/null; then
    curl -L -o "$INSTALL_PATH" "$TOOL_URL"
else
    echo -e "${RED}错误: 需要 wget 或 curl${NC}"
    exit 1
fi

echo -e "${YELLOW}[2/4] 设置可执行权限...${NC}"
chmod +x "$INSTALL_PATH"

echo -e "${YELLOW}[3/4] 验证安装...${NC}"
if [ -f "$INSTALL_PATH" ] && [ -x "$INSTALL_PATH" ]; then
    echo -e "${GREEN}✓ 安装成功${NC}"
else
    echo -e "${RED}✗ 安装失败${NC}"
    exit 1
fi

echo -e "${YELLOW}[4/4] 显示版本信息...${NC}"
"$INSTALL_PATH" --version || echo -e "${YELLOW}提示: 无法获取版本信息${NC}"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  安装完成！${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "安装位置: ${GREEN}$INSTALL_PATH${NC}"
echo ""
echo -e "使用方法:"
echo ""
echo -e "  ${GREEN}1. 部署伪授权${NC}"
echo -e "     ${YELLOW}virtualizor-tool license deploy${NC}"
echo -e "     ${YELLOW}virtualizor-tool license deploy --port 9443${NC}  # 指定端口"
echo ""
echo -e "  ${GREEN}2. 查看状态${NC}"
echo -e "     ${YELLOW}virtualizor-tool license status${NC}"
echo ""
echo -e "  ${GREEN}3. 卸载服务${NC}"
echo -e "     ${YELLOW}virtualizor-tool license uninstall${NC}"
echo ""

