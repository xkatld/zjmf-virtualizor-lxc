#!/bin/bash

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; NC='\033[0m'

log() { echo -e "$1"; }
ok() { log "${GREEN}[OK]${NC} $1"; }
info() { log "${BLUE}[INFO]${NC} $1"; }
warn() { log "${YELLOW}[WARN]${NC} $1"; }
err() { log "${RED}[ERR]${NC} $1"; exit 1; }

[[ $EUID -ne 0 ]] && err "请使用 root 运行"

echo
echo "========================================"
echo "  Virtualizor 授权配置脚本"
echo "========================================"
echo

DEFAULT_IP="152.53.227.142"

echo "说明:"
echo "  通过修改 /etc/hosts 文件，将 api.virtualizor.com"
echo "  指向指定 IP，实现 Virtualizor 授权绕过。"
echo

read -p "请输入授权服务器 IP [默认: $DEFAULT_IP]: " AUTH_IP
AUTH_IP=${AUTH_IP:-$DEFAULT_IP}

if [[ ! $AUTH_IP =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
    err "无效的 IP 地址格式: $AUTH_IP"
fi

echo
info "配置 Virtualizor 授权..."

if ! grep -q "api.virtualizor.com" /etc/hosts; then
    echo "$AUTH_IP api.virtualizor.com" >> /etc/hosts
    ok "已添加 Virtualizor 授权配置到 /etc/hosts"
else
    if grep -q "^[^#]*api.virtualizor.com" /etc/hosts && ! grep -q "$AUTH_IP api.virtualizor.com" /etc/hosts; then
        sed -i '/api.virtualizor.com/d' /etc/hosts
        echo "$AUTH_IP api.virtualizor.com" >> /etc/hosts
        ok "已更新 Virtualizor 授权配置"
    else
        ok "Virtualizor 授权配置已存在"
    fi
fi

echo
echo "========================================"
echo "  配置完成"
echo "========================================"
echo
echo "授权配置: $AUTH_IP api.virtualizor.com"
echo
echo "验证配置:"
echo "  cat /etc/hosts | grep api.virtualizor.com"
echo
ok "Virtualizor 授权配置已完成"
echo

