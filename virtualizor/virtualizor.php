<?php

use think\Db;

define('VIRTUALIZOR_DEBUG', true);

// 调试日志输出函数
function virtualizor_debug($message, $data = null) {
    if (!VIRTUALIZOR_DEBUG) return;
    $log = '[VIRTUALIZOR-DEBUG] ' . $message;
    if ($data !== null) {
        $log .= ' | Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    error_log($log);
}

// 插件元数据信息
function virtualizor_MetaData()
{
    return [
        'DisplayName' => '魔方财务-Virtualizor-Lxc对接插件 by xkatld',
        'APIVersion'  => '1.0.1',
        'HelpDoc'     => 'https://github.com/your-repo/virtualizor-api',
    ];
}

function virtualizor_ConfigOptions()
{
    return [
        'cores' => [
            'type'        => 'text',
            'name'        => 'CPU',
            'description' => '核心数量',
            'default'     => '1',
            'key'         => 'cores',
        ],
        'cpu' => [
            'type'        => 'text',
            'name'        => 'CPU权重',
            'description' => '默认1024',
            'default'     => '100',
            'key'         => 'cpu',
        ],
        'ram' => [
            'type'        => 'text',
            'name'        => '内存',
            'description' => '单位MB',
            'default'     => '512',
            'key'         => 'ram',
        ],
        'swap' => [
            'type'        => 'text',
            'name'        => 'SWAP',
            'description' => '单位MB，0表示等于内存',
            'default'     => '128',
            'key'         => 'swap',
        ],
        'space' => [
            'type'        => 'text',
            'name'        => '硬盘',
            'description' => '单位GB',
            'default'     => '1',
            'key'         => 'space',
        ],
        'osid' => [
            'type'        => 'text',
            'name'        => 'OS模板ID',
            'description' => 'Virtualizor系统模板ID',
            'default'     => '100001',
            'key'         => 'osid',
        ],
        'bandwidth' => [
            'type'        => 'text',
            'name'        => '月流量',
            'description' => '单位GB，0表示不限制',
            'default'     => '100',
            'key'         => 'bandwidth',
        ],
        'network_speed' => [
            'type'        => 'text',
            'name'        => '下载速度',
            'description' => '单位KB/s，0表示不限制',
            'default'     => '0',
            'key'         => 'network_speed',
        ],
        'upload_speed' => [
            'type'        => 'text',
            'name'        => '上传速度',
            'description' => '单位KB/s，0表示不限制',
            'default'     => '0',
            'key'         => 'upload_speed',
        ],
        'nat_enabled' => [
            'type'        => 'dropdown',
            'name'        => 'NAT端口转发',
            'description' => '启用后可添加端口映射',
            'default'     => 'true',
            'key'         => 'nat_enabled',
            'options'     => ['false' => '禁用', 'true' => '启用'],
        ],
        'nat_limit' => [
            'type'        => 'text',
            'name'        => 'NAT规则数量',
            'description' => '端口转发规则上限',
            'default'     => '5',
            'key'         => 'nat_limit',
        ],
        'udp_enabled' => [
            'type'        => 'dropdown',
            'name'        => 'UDP协议',
            'description' => '允许UDP端口转发',
            'default'     => 'false',
            'key'         => 'udp_enabled',
            'options'     => ['false' => '禁用', 'true' => '启用'],
        ],
        'ipv6_enabled' => [
            'type'        => 'dropdown',
            'name'        => '独立IPv6',
            'description' => '额外分配公网IPv6',
            'default'     => 'false',
            'key'         => 'ipv6_enabled',
            'options'     => ['false' => '禁用', 'true' => '启用'],
        ],
        'ipv6_limit' => [
            'type'        => 'text',
            'name'        => 'IPv6数量',
            'description' => 'IPv6地址上限',
            'default'     => '1',
            'key'         => 'ipv6_limit',
        ],
        'proxy_enabled' => [
            'type'        => 'dropdown',
            'name'        => 'Nginx反向代理',
            'description' => '启用后可绑定域名',
            'default'     => 'false',
            'key'         => 'proxy_enabled',
            'options'     => ['false' => '禁用', 'true' => '启用'],
        ],
        'proxy_limit' => [
            'type'        => 'text',
            'name'        => '域名数量',
            'description' => '域名绑定上限',
            'default'     => '1',
            'key'         => 'proxy_limit',
        ],
    ];
}

// 测试API连接
function virtualizor_TestLink($params)
{
    virtualizor_debug('开始测试API连接', $params);

    $data = [
        'url'  => '/api/check',
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];

    $res = virtualizor_Curl($params, $data, 'GET');

    if ($res === null) {
        return [
            'status' => 200,
            'data'   => [
                'server_status' => 0,
                'msg'           => "连接失败: 无法连接到服务器"
            ]
        ];
    } elseif (isset($res['error'])) {
        return [
            'status' => 200,
            'data'   => [
                'server_status' => 0,
                'msg'           => "连接失败: " . $res['error']
            ]
        ];
    } elseif (isset($res['code']) && $res['code'] == 200) {
        return [
            'status' => 200,
            'data'   => [
                'server_status' => 1,
                'msg'           => "连接成功"
            ]
        ];
    } elseif (isset($res['code'])) {
        return [
            'status' => 200,
            'data'   => [
                'server_status' => 0,
                'msg'           => "连接失败: " . ($res['msg'] ?? '服务器返回错误')
            ]
        ];
    } else {
        return [
            'status' => 200,
            'data'   => [
                'server_status' => 0,
                'msg'           => "连接失败: 响应格式异常"
            ]
        ];
    }
}

// 创建Virtualizor容器
function virtualizor_CreateAccount($params)
{
    virtualizor_debug('开始创建容器', ['domain' => $params['domain']]);

    $sys_pwd = $params['password'] ?? randStr(8);

    $ram = (int)($params['configoptions']['ram'] ?? 512);
    $swap = (int)($params['configoptions']['swap'] ?? 0);
    if ($swap == 0) {
        $swap = $ram;
    }

    $createData = [
        'virt'          => 'lxc',
        'hostname'      => $params['domain'],
        'rootpass'      => $sys_pwd,
        'cores'         => (int)($params['configoptions']['cores'] ?? 1),
        'ram'           => $ram,
        'swap'          => $swap,
        'space'         => (int)($params['configoptions']['space'] ?? 10),
        'osid'          => (int)($params['configoptions']['osid'] ?? 301),
        'bandwidth'     => (int)($params['configoptions']['bandwidth'] ?? 0),
        'uid'           => '0',
        'user_email'    => $params['domain'] . '@vps.local',
        'user_pass'     => $sys_pwd,
    ];

    if (!empty($params['configoptions']['cpu'])) {
        $createData['cpu'] = (int)$params['configoptions']['cpu'];
    }
    if (!empty($params['configoptions']['network_speed'])) {
        $createData['network_speed'] = (int)$params['configoptions']['network_speed'];
    }
    if (!empty($params['configoptions']['upload_speed'])) {
        $createData['upload_speed'] = (int)$params['configoptions']['upload_speed'];
    }
    
    $createData['num_ips'] = 1;
    $createData['num_ips6'] = 1;
    $createData['num_ips6_subnet'] = 0;
    $createData['osreinstall_limit'] = 0;

    $data = [
        'url'  => '/api/create',
        'type' => 'application/json',
        'data' => $createData,
    ];

    $res = virtualizor_JSONCurl($params, $data, 'POST');

    if (isset($res['code']) && $res['code'] == '200') {
        $dedicatedip_value = $params['server_ip'];

        $update = [
            'dedicatedip'  => $dedicatedip_value,
            'domainstatus' => 'Active',
            'username'     => 'root',
        ];

        try {
            Db::name('host')->where('id', $params['hostid'])->update($update);
            virtualizor_debug('数据库更新成功', $update);
        } catch (\Exception $e) {
             return ['status' => 'error', 'msg' => ($res['msg'] ?? '创建成功，但同步数据到面板失败: ' . $e->getMessage())];
        }

        return ['status' => 'success', 'msg' => $res['msg'] ?? '创建成功'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? '创建失败'];
    }
}

// 同步容器信息
function virtualizor_Sync($params)
{
    $data = [
        'url'  => '/api/status?hostname=' . $params['domain'],
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];
    $res = virtualizor_Curl($params, $data, 'GET');

    if (isset($res['code']) && $res['code'] == '200') {
        if (class_exists('think\Db') && isset($params['hostid'])) {
            try {
                $dedicatedip_value = $params['server_ip'];
                
                $update_data = [
                    'dedicatedip' => $dedicatedip_value,
                ];

                Db::name('host')->where('id', $params['hostid'])->update($update_data);
            } catch (Exception $e) {
                virtualizor_debug('同步数据库失败', ['error' => $e->getMessage()]);
            }
        }
        return ['status' => 'success', 'msg' => $res['msg'] ?? '同步成功'];
    }

    return ['status' => 'error', 'msg' => $res['msg'] ?? '同步失败'];
}

// 删除Virtualizor容器
function virtualizor_TerminateAccount($params)
{
    $data = [
        'url'  => '/api/delete?hostname=' . $params['domain'],
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];
    $res = virtualizor_Curl($params, $data, 'GET');

    return isset($res['code']) && $res['code'] == '200'
        ? ['status' => 'success', 'msg' => $res['msg'] ?? '删除成功']
        : ['status' => 'error', 'msg' => $res['msg'] ?? '删除失败'];
}

// 启动Virtualizor容器
function virtualizor_On($params)
{
    $data = [
        'url'  => '/api/boot?hostname=' . $params['domain'],
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];
    $res = virtualizor_Curl($params, $data, 'GET');

    return isset($res['code']) && $res['code'] == '200'
        ? ['status' => 'success', 'msg' => $res['msg'] ?? '开机成功']
        : ['status' => 'error', 'msg' => $res['msg'] ?? '开机失败'];
}

// 关闭Virtualizor容器
function virtualizor_Off($params)
{
    $data = [
        'url'  => '/api/stop?' . 'hostname=' . $params['domain'],
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];
    $res = virtualizor_Curl($params, $data, 'GET');

    if (isset($res['code']) && $res['code'] == '200') {
        return ['status' => 'success', 'msg' => $res['msg'] ?? '关机成功'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? '关机失败'];
    }
}

// 暂停Virtualizor容器
function virtualizor_SuspendAccount($params)
{
    virtualizor_debug('开始暂停容器', ['domain' => $params['domain']]);

    $data = [
        'url'  => '/api/suspend?hostname=' . $params['domain'],
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];
    $res = virtualizor_Curl($params, $data, 'GET');

    if (isset($res['code']) && $res['code'] == '200') {
        return ['status' => 'success', 'msg' => $res['msg'] ?? '容器暂停任务已提交'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? '容器暂停失败'];
    }
}

// 恢复Virtualizor容器
function virtualizor_UnsuspendAccount($params)
{
    virtualizor_debug('开始解除暂停容器', ['domain' => $params['domain']]);

    $data = [
        'url'  => '/api/unsuspend?hostname=' . $params['domain'],
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];
    $res = virtualizor_Curl($params, $data, 'GET');

    if (isset($res['code']) && $res['code'] == '200') {
        return ['status' => 'success', 'msg' => $res['msg'] ?? '容器恢复任务已提交'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? '容器恢复失败'];
    }
}

// 重启Virtualizor容器
function virtualizor_Reboot($params)
{
    $data = [
        'url'  => '/api/reboot?' . 'hostname=' . $params['domain'],
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];
    $res = virtualizor_Curl($params, $data, 'GET');

    if (isset($res['code']) && $res['code'] == '200') {
        return ['status' => 'success', 'msg' => $res['msg'] ?? '重启成功'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? '重启失败'];
    }
}

// 查询容器运行状态
function virtualizor_Status($params)
{
    $data = [
        'url'  => '/api/status?' . 'hostname=' . $params['domain'],
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];
    $res = virtualizor_Curl($params, $data, 'GET');

    if (isset($res['code']) && $res['code'] == 200) {
        $result = ['status' => 'success'];

        $containerStatus = $res['data']['status'] ?? '';

        switch (strtoupper($containerStatus)) {
            case 'RUNNING':
                $result['data']['status'] = 'on';
                $result['data']['des'] = '开机';
                break;
            case 'STOPPED':
                $result['data']['status'] = 'off';
                $result['data']['des'] = '关机';
                break;
            case 'FROZEN':
                $result['data']['status'] = 'suspend';
                $result['data']['des'] = '暂停';
                break;
            default:
                $result['data']['status'] = 'unknown';
                $result['data']['des'] = '未知状态';
                break;
        }

        return $result;
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? '获取状态失败'];
    }
}

// 重置容器密码
function virtualizor_CrackPassword($params, $new_pass)
{
    $data = [
        'url'  => '/api/password',
        'type' => 'application/json',
        'data' => [
            'hostname' => $params['domain'],
            'password' => $new_pass,
        ],
    ];
    $res = virtualizor_JSONCurl($params, $data, 'POST');

    if (isset($res['code']) && $res['code'] == 200) {
        try {
            Db::name('host')->where('id', $params['hostid'])->update(['password' => $new_pass]);
        } catch (\Exception $e) {
            return ['status' => 'error', 'msg' => 'Virtualizor容器密码重置成功，但同步新密码到面板数据库失败: ' . $e->getMessage()];
        }
        return ['status' => 'success', 'msg' => $res['msg'] ?? '密码重置成功'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? '密码重置失败'];
    }
}

// 重装容器操作系统
function virtualizor_Reinstall($params)
{
    if (empty($params['reinstall_os'])) {
        return ['status' => 'error', 'msg' => '操作系统参数错误'];
    }

    $reinstall_pass = $params['password'] ?? randStr(8);

    $data = [
        'url'  => '/api/reinstall',
        'type' => 'application/json',
        'data' => [
            'hostname' => $params['domain'],
            'osid'     => $params['reinstall_os'],
            'password' => $reinstall_pass,
        ],
    ];
    $res = virtualizor_JSONCurl($params, $data, 'POST');

    if (isset($res['code']) && $res['code'] == 200) {
        return ['status' => 'success', 'msg' => $res['msg'] ?? '重装成功'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? '重装失败'];
    }
}

// 客户区页面定义
function virtualizor_ClientArea($params)
{
    $pages = [
        'info' => ['name' => '产品信息'],
    ];
    
    $nat_enabled = ($params['configoptions']['nat_enabled'] ?? 'false') === 'true';
    if ($nat_enabled) {
        $pages['nat_acl'] = ['name' => 'NAT转发'];
    }
    
    $ipv6_enabled = ($params['configoptions']['ipv6_enabled'] ?? 'false') === 'true';
    if ($ipv6_enabled) {
        $pages['ipv6_acl'] = ['name' => 'IPv6绑定'];
    }
    
    $proxy_enabled = ($params['configoptions']['proxy_enabled'] ?? 'false') === 'true';
    if ($proxy_enabled) {
        $pages['proxy_acl'] = ['name' => '反向代理'];
    }
    
    return $pages;
}

// 客户区页面输出
function virtualizor_ClientAreaOutput($params, $key)
{
    virtualizor_debug('ClientAreaOutput调用', ['key' => $key, 'action' => $_GET['action'] ?? null]);

    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        virtualizor_debug('处理API请求', ['action' => $action, 'domain' => $params['domain'] ?? null]);

        if (empty($params['domain'])) {
            header('Content-Type: application/json');
            echo json_encode(['code' => 400, 'msg' => '容器域名未设置']);
            exit;
        }

        if ($action === 'natcheck') {
            header('Content-Type: application/json');
            echo json_encode(virtualizor_natcheck($params));
            exit;
        }
        
        if ($action === 'proxycheck') {
            header('Content-Type: application/json');
            echo json_encode(virtualizor_proxycheck($params));
            exit;
        }

        $apiEndpoints = [
            'getinfo'    => '/api/status',
            'getstats'   => '/api/info',
            'getinfoall' => '/api/info',
            'natlist'    => '/api/natlist',
            'ipv6list'   => '/api/ipv6/list',
            'proxylist'  => '/api/proxy/list',
        ];

        $apiEndpoint = $apiEndpoints[$action] ?? '';

        if (!$apiEndpoint) {
            header('Content-Type: application/json');
            echo json_encode(['code' => 400, 'msg' => '不支持的操作: ' . $action]);
            exit;
        }

        $requestData = [
            'url'  => $apiEndpoint . '?hostname=' . $params['domain'],
            'type' => 'application/x-www-form-urlencoded',
            'data' => [],
        ];

        $res = virtualizor_Curl($params, $requestData, 'GET');

        if ($res === null) {
            $res = ['code' => 500, 'msg' => '连接服务器失败'];
        } elseif (!is_array($res)) {
            $res = ['code' => 500, 'msg' => '服务器返回了无效的响应格式'];
        } else {
            $res = virtualizor_TransformAPIResponse($action, $res, $params);
            
            // 只为容器信息类请求添加 cpu_cores
            if (in_array($action, ['getinfo', 'getstats', 'getinfoall']) && isset($res['data']) && !isset($res['data']['cpu_cores'])) {
                $res['data']['cpu_cores'] = intval($params['configoptions']['cores'] ?? 1);
            }
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo json_encode($res);
        exit;
    }

    if ($key == 'info') {
        return [
            'template' => 'templates/info.html',
            'vars'     => [],
        ];
    }

    if ($key == 'nat_acl') {
        $nat_enabled = ($params['configoptions']['nat_enabled'] ?? 'false') === 'true';
        
        $requestData = [
            'url'  => '/api/natlist?hostname=' . $params['domain'] . '&_t=' . time(),
            'type' => 'application/x-www-form-urlencoded',
            'data' => [],
        ];
        $res = virtualizor_Curl($params, $requestData, 'GET');

        $nat_limit = intval($params['configoptions']['nat_limit'] ?? 5);
        $current_count = virtualizor_getNATRuleCount($params);
        $udp_enabled = ($params['configoptions']['udp_enabled'] ?? 'false') === 'true';

        return [
            'template' => 'templates/nat.html',
            'vars'     => [
                'list' => $res['data'] ?? [],
                'msg'  => $res['msg'] ?? '',
                'nat_limit' => $nat_limit,
                'current_count' => $current_count,
                'remaining_count' => max(0, $nat_limit - $current_count),
                'udp_enabled' => $udp_enabled,
                'nat_enabled' => $nat_enabled,
            ],
        ];
    }
    
    if ($key == 'ipv6_acl') {
        $ipv6_enabled = ($params['configoptions']['ipv6_enabled'] ?? 'false') === 'true';
        
        $requestData = [
            'url'  => '/api/ipv6/list?hostname=' . $params['domain'] . '&_t=' . time(),
            'type' => 'application/x-www-form-urlencoded',
            'data' => [],
        ];
        $res = virtualizor_Curl($params, $requestData, 'GET');

        $ipv6_limit = intval($params['configoptions']['ipv6_limit'] ?? 1);
        $current_count = virtualizor_getIPv6BindingCount($params);

        return [
            'template' => 'templates/ipv6.html',
            'vars'     => [
                'list' => $res['data'] ?? [],
                'msg'  => $res['msg'] ?? '',
                'ipv6_limit' => $ipv6_limit,
                'current_count' => $current_count,
                'remaining_count' => max(0, $ipv6_limit - $current_count),
                'container_name' => $params['domain'],
                'ipv6_enabled' => $ipv6_enabled,
            ],
        ];
    }
    
    if ($key == 'proxy_acl') {
        $proxy_enabled = ($params['configoptions']['proxy_enabled'] ?? 'false') === 'true';
        
        $requestData = [
            'url'  => '/api/proxy/list?hostname=' . $params['domain'] . '&_t=' . time(),
            'type' => 'application/x-www-form-urlencoded',
            'data' => [],
        ];
        $res = virtualizor_Curl($params, $requestData, 'GET');

        $proxy_limit = intval($params['configoptions']['proxy_limit'] ?? 1);
        $current_count = is_array($res['data']) ? count($res['data']) : 0;
        
        return [
            'template' => 'templates/proxy.html',
            'vars'     => [
                'list' => $res['data'] ?? [],
                'msg'  => $res['msg'] ?? '',
                'proxy_limit' => $proxy_limit,
                'current_count' => $current_count,
                'remaining_count' => max(0, $proxy_limit - $current_count),
                'container_name' => $params['domain'],
                'proxy_enabled' => $proxy_enabled,
            ],
        ];
    }
}

// 允许客户端调用的函数列表
function virtualizor_AllowFunction()
{
    return [
        'client' => ['vnc', 'natadd', 'natdel', 'natlist', 'natcheck', 'ipv6add', 'ipv6del', 'ipv6list', 'proxyadd', 'proxydel', 'proxylist', 'proxycheck'],
    ];
}

// LXC Console - 容器控制台（通过 lxc-console）
function virtualizor_vnc($params)
{
    virtualizor_debug('LXC Console 请求', ['domain' => $params['domain']]);

    $tokenData = [
        'url'  => '/api/terminal/token?hostname=' . urlencode($params['domain']),
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];

    $tokenRes = virtualizor_Curl($params, $tokenData, 'GET');
    
    if (!isset($tokenRes['code']) || $tokenRes['code'] != 200) {
        return [
            'status' => 'error',
            'msg' => $tokenRes['msg'] ?? '生成访问令牌失败'
        ];
    }
    
    $token = $tokenRes['data']['token'] ?? '';
    if (empty($token)) {
        return [
            'status' => 'error',
            'msg' => '获取访问令牌失败'
        ];
    }
    
    $protocol = 'https';
    $consoleUrl = $protocol . '://' . $params['server_ip'] . ':' . $params['port'] . '/api/terminal?token=' . urlencode($token);

    return [
        'status' => 'success',
        'url' => $consoleUrl,
        'msg' => '正在打开容器控制台...'
    ];
}

// 发送JSON格式的cURL请求
function virtualizor_JSONCurl($params, $data = [], $request = 'POST')
{
    $curl = curl_init();

    $protocol = 'https';
    $url = $protocol . '://' . $params['server_ip'] . ':' . $params['port'] . $data['url'];

    $curlOptions = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => $request,
        CURLOPT_POSTFIELDS     => json_encode($data['data']),
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $params['accesshash'],
            'Content-Type: application/json',
        ],
    ];

    $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
    $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
    $curlOptions[CURLOPT_SSLVERSION] = CURL_SSLVERSION_TLSv1_2;

    curl_setopt_array($curl, $curlOptions);

    $response = curl_exec($curl);
    $errno    = curl_errno($curl);

    curl_close($curl);

    if ($errno) {
        return null;
    }

    return json_decode($response, true);
}

// 发送通用的cURL请求
function virtualizor_Curl($params, $data = [], $request = 'POST')
{
    $curl = curl_init();

    $protocol = 'https';
    $url = $protocol . '://' . $params['server_ip'] . ':' . $params['port'] . $data['url'];


    $postFields = ($request === 'POST' || $request === 'PUT') ? ($data['data'] ?? null) : null;
    if ($request === 'GET' && !empty($data['data']) && is_array($data['data'])) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data['data']);
    } elseif ($request === 'GET' && !empty($data['data']) && is_string($data['data'])) {
         $url .= (strpos($url, '?') === false ? '?' : '&') . $data['data'];
    }

    $curlOptions = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => $request,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $params['accesshash'],
            'Content-Type: ' . ($data['type'] ?? 'application/x-www-form-urlencoded'),
        ],
    ];

    $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
    $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
    $curlOptions[CURLOPT_SSLVERSION] = CURL_SSLVERSION_TLSv1_2;

    curl_setopt_array($curl, $curlOptions);

    if ($postFields !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
    }

    $response = curl_exec($curl);
    $errno    = curl_errno($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);

    curl_close($curl);


    if ($errno) {
        return null;
    }

    $decoded = json_decode($response, true);
    return $decoded;
}

// 转换API响应以适配前端
function virtualizor_TransformAPIResponse($action, $response, $params = [])
{
    if (isset($response['error'])) {
        return [
            'code' => 400,
            'msg' => $response['error']
        ];
    }

    if (!isset($response['code']) || $response['code'] != 200) {
        return $response;
    }

    switch ($action) {
        case 'getinfo':
        case 'getstats':
        case 'getinfoall':
            if (isset($response['data'])) {
                $data = $response['data'];

                $cpuUsage = floatval($data['used_cpu'] ?? 0);
                $cpuCores = intval($data['cores'] ?? 1);
                $memoryUsed = intval($data['used_ram'] ?? 0);
                $memoryTotal = intval($data['ram'] ?? 1024);
                $diskUsed = floatval($data['used_disk'] ?? 0);
                $diskTotal = floatval($data['disk'] ?? 10);
                $bandwidthUsed = floatval($data['used_bandwidth'] ?? 0);
                $bandwidthTotal = floatval($data['bandwidth'] ?? 0);

                $memoryPercent = $memoryTotal > 0 ? round(($memoryUsed / $memoryTotal) * 100, 2) : 0;
                $diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 2) : 0;
                $trafficPercent = $bandwidthTotal > 0 ? round(($bandwidthUsed / $bandwidthTotal) * 100, 2) : 0;
                
                $transformed = [
                    'code' => 200,
                    'msg' => '获取容器信息成功',
                    'data' => [
                        'vpsid' => $data['vpsid'] ?? '',
                        'hostname' => $params['domain'] ?? ($data['hostname'] ?? ''),
                        'status' => $data['status'] ?? 0,
                        'virt' => $data['virt'] ?? 'lxc',

                        'cpu_usage' => $cpuUsage,
                        'cpu_percent' => $cpuUsage,
                        'cpu_cores' => $cpuCores,

                        'memory' => $memoryTotal,
                        'memory_used' => $memoryUsed,
                        'memory_usage' => $memoryUsed . ' MB',
                        'memory_usage_raw' => $memoryUsed * 1024 * 1024,
                        'memory_percent' => $memoryPercent,
                        'ram' => $memoryTotal . ' MB',

                        'disk' => $diskTotal . ' GB',
                        'disk_used' => $diskUsed,
                        'disk_usage' => $diskUsed . ' GB',
                        'disk_usage_raw' => $diskUsed * 1024 * 1024 * 1024,
                        'disk_percent' => $diskPercent,

                        'bandwidth' => $bandwidthTotal . ' GB',
                        'bandwidth_used' => $bandwidthUsed,
                        'traffic_usage' => $bandwidthUsed . ' GB',
                        'traffic_usage_raw' => $bandwidthUsed * 1024 * 1024 * 1024,
                        'traffic_percent' => $trafficPercent,
                        'used_bandwidth' => $bandwidthUsed,

                        'net_in' => $data['net_in'] ?? 0,
                        'net_out' => $data['net_out'] ?? 0,

                        'io_read' => $data['io_read'] ?? 0,
                        'io_write' => $data['io_write'] ?? 0,

                        'lock_status' => $data['lock_status'] ?? false,
                        'network_status' => $data['network_status'] ?? false,

                        'last_update' => date('Y-m-d H:i:s'),
                        'timestamp' => time(),
                    ]
                ];

                return $transformed;
            }
            break;
    }

    return $response;
}

// ============ NAT端口转发功能 ============

// 获取容器NAT规则数量
function virtualizor_getNATRuleCount($params)
{
    $data = [
        'url'  => '/api/natlist?hostname=' . urlencode($params['domain']),
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];

    $res = virtualizor_Curl($params, $data, 'GET');

    if (isset($res['code']) && $res['code'] == 200 && isset($res['data']) && is_array($res['data'])) {
        return count($res['data']);
    }

    return 0;
}

// 添加NAT端口转发
function virtualizor_natadd($params)
{
    $nat_enabled = ($params['configoptions']['nat_enabled'] ?? 'false') === 'true';
    if (!$nat_enabled) {
        return ['status' => 'error', 'msg' => 'NAT端口转发功能已禁用，请联系管理员启用此功能。'];
    }
    
    parse_str(file_get_contents("php://input"), $post);

    $dport = intval($post['dport'] ?? 0);
    $sport = intval($post['sport'] ?? 0);
    $dtype = strtolower(trim($post['dtype'] ?? ''));
    $description = trim($post['description'] ?? '');
    $udp_enabled = ($params['configoptions']['udp_enabled'] ?? 'false') === 'true';

    if (!in_array($dtype, ['tcp', 'udp'])) {
        return ['status' => 'error', 'msg' => '不支持的协议类型，仅支持TCP和UDP'];
    }
    
    if ($dtype === 'udp' && !$udp_enabled) {
        return ['status' => 'error', 'msg' => 'UDP协议未启用，请联系管理员开启UDP支持'];
    }
    if ($sport <= 0 || $sport > 65535) {
        return ['status' => 'error', 'msg' => '容器内部端口超过范围'];
    }

    $nat_limit = intval($params['configoptions']['nat_limit'] ?? 5);

    $current_count = virtualizor_getNATRuleCount($params);
    if ($current_count >= $nat_limit) {
        return ['status' => 'error', 'msg' => "NAT规则数量已达到限制（{$nat_limit}条），无法添加更多规则"];
    }

    $requestData = 'hostname=' . urlencode($params['domain']) . '&dtype=' . urlencode($dtype) . '&sport=' . $sport;

    if ($dport > 0) {
        if ($dport < 10000 || $dport > 65535) {
            return ['status' => 'error', 'msg' => '外网端口范围为10000-65535'];
        }
        $checkData = [
            'url'  => '/api/nat/check?hostname=' . urlencode($params['domain']) . '&protocol=' . urlencode($dtype) . '&port=' . $dport,
            'type' => 'application/x-www-form-urlencoded',
            'data' => [],
        ];
        $checkRes = virtualizor_Curl($params, $checkData, 'GET');
        if (!isset($checkRes['code']) || $checkRes['code'] != 200 || empty($checkRes['data']['available'])) {
            $reason = $checkRes['data']['reason'] ?? $checkRes['msg'] ?? '端口不可用';
            return ['status' => 'error', 'msg' => $reason];
        }
        $requestData .= '&dport=' . $dport;
    }
    
    if (!empty($description)) {
        $requestData .= '&description=' . urlencode($description);
    }

    $data = [
        'url'  => '/api/addport',
        'type' => 'application/x-www-form-urlencoded',
        'data' => $requestData,
    ];

    $res = virtualizor_Curl($params, $data, 'POST');

    if (isset($res['code']) && $res['code'] == 200) {
        return ['status' => 'success', 'msg' => $res['msg'] ?? 'NAT转发添加成功'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? 'NAT转发添加失败'];
    }
}

// 删除NAT端口转发
function virtualizor_natdel($params)
{
    parse_str(file_get_contents("php://input"), $post);

    $dport = intval($post['dport'] ?? 0);
    $sport = intval($post['sport'] ?? 0);
    $dtype = strtolower(trim($post['dtype'] ?? ''));
    $udp_enabled = ($params['configoptions']['udp_enabled'] ?? 'false') === 'true';

    if (!in_array($dtype, ['tcp', 'udp'])) {
        return ['status' => 'error', 'msg' => '不支持的协议类型，仅支持TCP和UDP'];
    }
    
    if ($dtype === 'udp' && !$udp_enabled) {
        // UDP已禁用但允许删除已存在的UDP规则
    }
    if ($sport <= 0 || $sport > 65535) {
        return ['status' => 'error', 'msg' => '容器内部端口超过范围'];
    }
    if ($dport < 10000 || $dport > 65535) {
        return ['status' => 'error', 'msg' => '外网端口映射范围为10000-65535'];
    }

    $data = [
        'url'  => '/api/delport',
        'type' => 'application/x-www-form-urlencoded',
        'data' => 'hostname=' . urlencode($params['domain']) . '&dtype=' . urlencode($dtype) . '&dport=' . $dport . '&sport=' . $sport,
    ];

    $res = virtualizor_Curl($params, $data, 'POST');

    if (isset($res['code']) && $res['code'] == 200) {
        return ['status' => 'success', 'msg' => $res['msg'] ?? 'NAT转发删除成功'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? 'NAT转发删除失败'];
    }
}

// 获取NAT规则列表
function virtualizor_natlist($params)
{
    $requestData = [
        'url'  => '/api/natlist?hostname=' . $params['domain'] . '&_t=' . time(),
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];
    $res = virtualizor_Curl($params, $requestData, 'GET');
    if ($res === null) {
        return ['code' => 500, 'msg' => '连接API服务器失败', 'data' => []];
    }
    return $res;
}

// 检查NAT端口是否可用
function virtualizor_natcheck($params)
{
    // 先尝试从URL查询参数获取
    $dport = intval($_GET['dport'] ?? 0);
    $dtype = strtolower(trim($_GET['dtype'] ?? ''));
    $hostname = trim($_GET['hostname'] ?? '');

    // 如果GET参数为空，尝试从POST获取
    if ($dport <= 0) {
        $dport = intval($_POST['dport'] ?? 0);
    }
    if (empty($dtype)) {
        $dtype = strtolower(trim($_POST['dtype'] ?? 'tcp'));
    }
    if (empty($hostname)) {
        $hostname = trim($_POST['hostname'] ?? '');
    }

    // 如果还是为空，尝试从原始POST数据解析
    if ($dport <= 0 || empty($hostname)) {
        $postRaw = file_get_contents("php://input");
        if (!empty($postRaw)) {
            parse_str($postRaw, $input);
            if ($dport <= 0) {
                $dport = intval($input['dport'] ?? 0);
            }
            if (empty($dtype)) {
                $dtype = strtolower(trim($input['dtype'] ?? 'tcp'));
            }
            if (empty($hostname)) {
                $hostname = trim($input['hostname'] ?? '');
            }
        }
    }

    // 如果hostname还是空，使用params中的domain
    if (empty($hostname)) {
        $hostname = trim($params['domain'] ?? '');
    }

    virtualizor_debug('natcheck参数解析', [
        'dport' => $dport, 
        'dtype' => $dtype, 
        'hostname' => $hostname,
        'GET' => $_GET,
        'POST' => $_POST,
        'params_domain' => $params['domain'] ?? 'null'
    ]);

    // 参数验证
    if ($dport <= 0) {
        return ['code' => 400, 'msg' => '缺少端口参数', 'data' => ['available' => false, 'reason' => '缺少端口参数']];
    }
    if (!in_array($dtype, ['tcp', 'udp'])) {
        return ['code' => 400, 'msg' => '协议类型错误', 'data' => ['available' => false, 'reason' => '协议类型错误']];
    }
    if ($dport < 10000 || $dport > 65535) {
        return ['code' => 400, 'msg' => '端口范围为10000-65535', 'data' => ['available' => false, 'reason' => '端口范围为10000-65535']];
    }
    if (empty($hostname)) {
        return ['code' => 400, 'msg' => '容器标识缺失', 'data' => ['available' => false, 'reason' => '容器标识缺失']];
    }

    // 使用GET请求调用后端API
    $queryParams = http_build_query([
        'hostname' => $hostname,
        'protocol' => $dtype,
        'port'     => $dport,
    ]);

    $requestData = [
        'url'  => '/api/nat/check?' . $queryParams,
        'type' => 'application/x-www-form-urlencoded',
        'data' => '',
    ];

    $res = virtualizor_Curl($params, $requestData, 'GET');

    if ($res === null) {
        return ['code' => 500, 'msg' => '连接服务器失败', 'data' => ['available' => false, 'reason' => '连接服务器失败']];
    }

    if (!isset($res['code'])) {
        return ['code' => 500, 'msg' => '服务器返回无效数据', 'data' => ['available' => false, 'reason' => '服务器返回无效数据']];
    }

    return $res;
}

// ========== IPv6 独立绑定功能 ==========

// 添加IPv6绑定
function virtualizor_ipv6add($params)
{
    $ipv6_enabled = ($params['configoptions']['ipv6_enabled'] ?? 'false') === 'true';
    if (!$ipv6_enabled) {
        return ['status' => 'error', 'msg' => 'IPv6独立绑定功能未启用，请联系管理员启用此功能。'];
    }
    
    parse_str(file_get_contents("php://input"), $post);

    $description = trim($post['description'] ?? '');

    $ipv6_limit = intval($params['configoptions']['ipv6_limit'] ?? 1);
    $current_count = virtualizor_getIPv6BindingCount($params);
    
    if ($current_count >= $ipv6_limit) {
        return ['status' => 'error', 'msg' => "IPv6绑定数量已达到限制（{$ipv6_limit}个），无法添加更多绑定"];
    }

    $requestData = 'hostname=' . urlencode($params['domain']) . '&description=' . urlencode($description);

    $data = [
        'url'  => '/api/ipv6/add',
        'type' => 'application/x-www-form-urlencoded',
        'data' => $requestData,
    ];

    $res = virtualizor_Curl($params, $data, 'POST');

    if (isset($res['code']) && $res['code'] == 200) {
        return ['status' => 'success', 'msg' => $res['msg'] ?? 'IPv6绑定添加成功'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? 'IPv6绑定添加失败'];
    }
}

// 删除IPv6绑定
function virtualizor_ipv6del($params)
{
    $ipv6_enabled = ($params['configoptions']['ipv6_enabled'] ?? 'false') === 'true';
    if (!$ipv6_enabled) {
        return ['status' => 'error', 'msg' => 'IPv6独立绑定功能已禁用'];
    }
    
    parse_str(file_get_contents("php://input"), $post);

    $public_ipv6 = trim($post['public_ipv6'] ?? '');

    if (empty($public_ipv6)) {
        return ['status' => 'error', 'msg' => '请提供要删除的IPv6地址'];
    }

    $requestData = 'hostname=' . urlencode($params['domain']) . '&public_ipv6=' . urlencode($public_ipv6);

    $data = [
        'url'  => '/api/ipv6/delete',
        'type' => 'application/x-www-form-urlencoded',
        'data' => $requestData,
    ];

    $res = virtualizor_Curl($params, $data, 'POST');

    if (isset($res['code']) && $res['code'] == 200) {
        return ['status' => 'success', 'msg' => $res['msg'] ?? 'IPv6绑定删除成功'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? 'IPv6绑定删除失败'];
    }
}

// 获取IPv6绑定列表
function virtualizor_ipv6list($params)
{
    $requestData = [
        'url'  => '/api/ipv6/list?hostname=' . $params['domain'] . '&_t=' . time(),
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];
    $res = virtualizor_Curl($params, $requestData, 'GET');
    if ($res === null) {
        return ['code' => 500, 'msg' => '连接API服务器失败', 'data' => []];
    }
    return $res;
}

// 获取IPv6绑定数量
function virtualizor_getIPv6BindingCount($params)
{
    $res = virtualizor_ipv6list($params);
    return is_array($res['data'] ?? null) ? count($res['data']) : 0;
}

// ========== Nginx 反向代理功能 ==========

// 添加反向代理
function virtualizor_proxyadd($params)
{
    $proxy_enabled = ($params['configoptions']['proxy_enabled'] ?? 'false') === 'true';
    if (!$proxy_enabled) {
        return ['status' => 'error', 'msg' => 'Nginx反向代理功能已禁用，请联系管理员启用此功能。'];
    }
    
    parse_str(file_get_contents("php://input"), $post);

    $domain = trim($post['domain'] ?? '');
    $container_port = intval($post['container_port'] ?? 80);
    $description = trim($post['description'] ?? '');
    $ssl_enabled = ($post['ssl_enabled'] ?? 'false') === 'true';
    $ssl_type = trim($post['ssl_type'] ?? 'self-signed');
    $ssl_cert = trim($post['ssl_cert'] ?? '');
    $ssl_key = trim($post['ssl_key'] ?? '');

    if (empty($domain)) {
        return ['status' => 'error', 'msg' => '请提供域名'];
    }

    if ($container_port <= 0 || $container_port > 65535) {
        return ['status' => 'error', 'msg' => '容器端口范围为1-65535'];
    }

    $proxy_limit = intval($params['configoptions']['proxy_limit'] ?? 1);
    $current_count = virtualizor_getProxyCount($params);
    
    if ($current_count >= $proxy_limit) {
        return ['status' => 'error', 'msg' => "已达到反向代理数量上限（{$proxy_limit}个），无法继续添加"];
    }

    if ($ssl_enabled && $ssl_type === 'custom' && (empty($ssl_cert) || empty($ssl_key))) {
        return ['status' => 'error', 'msg' => '启用自定义SSL证书时，必须提供证书和私钥内容'];
    }

    $requestData = 'hostname=' . urlencode($params['domain']) 
        . '&domain=' . urlencode($domain)
        . '&container_port=' . $container_port
        . '&description=' . urlencode($description)
        . '&ssl_enabled=' . ($ssl_enabled ? 'true' : 'false')
        . '&ssl_type=' . urlencode($ssl_type);

    if ($ssl_enabled && $ssl_type === 'custom') {
        $requestData .= '&ssl_cert=' . urlencode($ssl_cert) . '&ssl_key=' . urlencode($ssl_key);
    }

    $data = [
        'url'  => '/api/proxy/add',
        'type' => 'application/x-www-form-urlencoded',
        'data' => $requestData,
    ];

    $res = virtualizor_Curl($params, $data, 'POST');

    if (isset($res['code']) && $res['code'] == 200) {
        return ['status' => 'success', 'msg' => $res['msg'] ?? '反向代理添加成功'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? '反向代理添加失败'];
    }
}

// 删除反向代理
function virtualizor_proxydel($params)
{
    $proxy_enabled = ($params['configoptions']['proxy_enabled'] ?? 'false') === 'true';
    if (!$proxy_enabled) {
        return ['status' => 'error', 'msg' => 'Nginx反向代理功能已禁用'];
    }
    
    parse_str(file_get_contents("php://input"), $post);

    $domain = trim($post['domain'] ?? '');

    if (empty($domain)) {
        return ['status' => 'error', 'msg' => '请提供要删除的域名'];
    }

    $requestData = 'hostname=' . urlencode($params['domain']) . '&domain=' . urlencode($domain);

    $data = [
        'url'  => '/api/proxy/delete',
        'type' => 'application/x-www-form-urlencoded',
        'data' => $requestData,
    ];

    $res = virtualizor_Curl($params, $data, 'POST');

    if (isset($res['code']) && $res['code'] == 200) {
        return ['status' => 'success', 'msg' => $res['msg'] ?? '反向代理删除成功'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? '反向代理删除失败'];
    }
}

// 获取反向代理列表
function virtualizor_proxylist($params)
{
    $requestData = [
        'url'  => '/api/proxy/list?hostname=' . $params['domain'] . '&_t=' . time(),
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];
    $res = virtualizor_Curl($params, $requestData, 'GET');
    if ($res === null) {
        return ['code' => 500, 'msg' => '连接API服务器失败', 'data' => []];
    }
    return $res;
}

// 检查域名是否可用
function virtualizor_proxycheck($params)
{
    $domain = trim($_GET['domain'] ?? '');

    if (empty($domain)) {
        return ['code' => 400, 'msg' => '请提供域名', 'data' => ['available' => false, 'reason' => '域名不能为空']];
    }

    $requestData = [
        'url'  => '/api/proxy/check?domain=' . urlencode($domain) . '&_t=' . time(),
        'type' => 'application/x-www-form-urlencoded',
        'data' => [],
    ];
    $res = virtualizor_Curl($params, $requestData, 'GET');

    if ($res === null) {
        return ['code' => 500, 'msg' => '连接API服务器失败', 'data' => ['available' => false, 'reason' => '连接服务器失败']];
    }

    if (!isset($res['code'])) {
        return ['code' => 500, 'msg' => '服务器返回无效数据', 'data' => ['available' => false, 'reason' => '服务器返回无效数据']];
    }

    return $res;
}

// 获取反向代理数量
function virtualizor_getProxyCount($params)
{
    $res = virtualizor_proxylist($params);
    return is_array($res['data'] ?? null) ? count($res['data']) : 0;
}

