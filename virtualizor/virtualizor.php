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
        'DisplayName' => 'Virtualizor LXC',
        'APIVersion'  => '1.0.0',
        'HelpDoc'     => 'https://github.com/your-repo/virtualizor-api',
    ];
}

function virtualizor_ConfigOptions()
{
    return [
        'cores' => [
            'type'        => 'text',
            'name'        => 'CPU核心数',
            'description' => 'CPU核心数量',
            'default'     => '1',
            'key'         => 'cores',
        ],
        'cpu' => [
            'type'        => 'text',
            'name'        => 'CPU权重',
            'description' => 'CPU权重',
            'default'     => '1024',
            'key'         => 'cpu',
        ],
        'ram' => [
            'type'        => 'text',
            'name'        => '内存(MB)',
            'description' => '内存大小，单位MB',
            'default'     => '512',
            'key'         => 'ram',
        ],
        'swap' => [
            'type'        => 'text',
            'name'        => 'SWAP(MB)',
            'description' => 'SWAP大小，单位MB，0表示等于内存',
            'default'     => '0',
            'key'         => 'swap',
        ],
        'space' => [
            'type'        => 'text',
            'name'        => '硬盘(GB)',
            'description' => '硬盘大小，单位GB',
            'default'     => '10',
            'key'         => 'space',
        ],
        'osid' => [
            'type'        => 'text',
            'name'        => 'OS模板ID',
            'description' => 'Virtualizor OS模板ID',
            'default'     => '301',
            'key'         => 'osid',
        ],
        'bandwidth' => [
            'type'        => 'text',
            'name'        => '月流量(GB)',
            'description' => '月流量限制，0表示不限制',
            'default'     => '0',
            'key'         => 'bandwidth',
        ],
        'network_speed' => [
            'type'        => 'text',
            'name'        => '网络速度(KB/s)',
            'description' => '网络速度，单位KB/s，0表示不限制',
            'default'     => '0',
            'key'         => 'network_speed',
        ],
        'upload_speed' => [
            'type'        => 'text',
            'name'        => '上传速度(KB/s)',
            'description' => '上传速度限制，单位KB/s，0表示不限制',
            'default'     => '0',
            'key'         => 'upload_speed',
        ],
        'num_ips' => [
            'type'        => 'text',
            'name'        => 'IPv4数量',
            'description' => 'IPv4地址数量',
            'default'     => '1',
            'key'         => 'num_ips',
        ],
        'num_ips6' => [
            'type'        => 'text',
            'name'        => 'IPv6数量',
            'description' => 'IPv6地址数量',
            'default'     => '0',
            'key'         => 'num_ips6',
        ],
        'num_ips6_subnet' => [
            'type'        => 'text',
            'name'        => 'IPv6子网数量',
            'description' => 'IPv6子网数量',
            'default'     => '0',
            'key'         => 'num_ips6_subnet',
        ],
        'osreinstall_limit' => [
            'type'        => 'text',
            'name'        => '重装系统限制',
            'description' => '每月重装系统次数限制，0表示不限制',
            'default'     => '0',
            'key'         => 'osreinstall_limit',
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
    if (!empty($params['configoptions']['num_ips'])) {
        $createData['num_ips'] = (int)$params['configoptions']['num_ips'];
    }
    if (!empty($params['configoptions']['num_ips6'])) {
        $createData['num_ips6'] = (int)$params['configoptions']['num_ips6'];
    }
    if (!empty($params['configoptions']['num_ips6_subnet'])) {
        $createData['num_ips6_subnet'] = (int)$params['configoptions']['num_ips6_subnet'];
    }
    if (!empty($params['configoptions']['osreinstall_limit'])) {
        $createData['osreinstall_limit'] = (int)$params['configoptions']['osreinstall_limit'];
    }

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
            return ['status' => 'error', 'msg' => ($res['msg'] ?? $res['message'] ?? 'Virtualizor容器密码重置成功，但同步新密码到面板数据库失败: ' . $e->getMessage())];
        }
        return ['status' => 'success', 'msg' => $res['msg'] ?? $res['message'] ?? '密码重置成功'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? $res['message'] ?? '密码重置失败'];
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
            'system'   => $params['reinstall_os'],
            'password' => $reinstall_pass,
        ],
    ];
    $res = virtualizor_JSONCurl($params, $data, 'POST');

    if (isset($res['code']) && $res['code'] == 200) {
        return ['status' => 'success', 'msg' => $res['msg'] ?? $res['message'] ?? '重装成功'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? $res['message'] ?? '重装失败'];
    }
}

// 客户区页面定义
function virtualizor_ClientArea($params)
{
    return [
        'info' => ['name' => '产品信息'],
    ];
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

        $apiEndpoints = [
            'getinfo'    => '/api/status',
            'getstats'   => '/api/info',
            'getinfoall' => '/api/info',
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
            $res = virtualizor_TransformAPIResponse($action, $res);
            
            if (isset($res['data']) && !isset($res['data']['cpu_cores'])) {
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
}

// 允许客户端调用的函数列表
function virtualizor_AllowFunction()
{
    return [
        'client' => ['vnc'],
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
function virtualizor_TransformAPIResponse($action, $response)
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

