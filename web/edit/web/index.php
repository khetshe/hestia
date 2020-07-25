<?php
error_reporting(NULL);
ob_start();
unset($_SESSION['error_msg']);
$TAB = 'WEB';

// Main include
include($_SERVER['DOCUMENT_ROOT']."/inc/main.php");

// Check domain argument
if (empty($_GET['domain'])) {
    header("Location: /list/web/");
    exit;
}

// Edit as someone else?
if (($_SESSION['user'] == 'admin') && (!empty($_GET['user']))) {
    $user=escapeshellarg($_GET['user']);
}

// Get all user domains 
exec (HESTIA_CMD."v-list-web-domains ".escapeshellarg($user)." json", $output, $return_var);
$user_domains = json_decode(implode('', $output), true);
$user_domains = array_keys($user_domains);
unset($output);

// List domain
$v_domain = $_GET['domain'];
if(!in_array($v_domain, $user_domains)) {
    header("Location: /list/web/");
    exit;
}

exec (HESTIA_CMD."v-list-web-domain ".$user." ".escapeshellarg($v_domain)." json", $output, $return_var);
$data = json_decode(implode('', $output), true);
unset($output);

// Parse domain
$v_username = $user;
$v_ip = $data[$v_domain]['IP'];
$v_template = $data[$v_domain]['TPL'];
$v_aliases = str_replace(',', "\n", $data[$v_domain]['ALIAS']);
$valiases = explode(",", $data[$v_domain]['ALIAS']);

$v_tpl = $data[$v_domain]['IP'];
$v_cgi = $data[$v_domain]['CGI'];
$v_elog = $data[$v_domain]['ELOG'];
$v_ssl = $data[$v_domain]['SSL'];
if (!empty($v_ssl)) {
    exec (HESTIA_CMD."v-list-web-domain-ssl ".$user." ".escapeshellarg($v_domain)." json", $output, $return_var);
    $ssl_str = json_decode(implode('', $output), true);
    unset($output);
    $v_ssl_crt = $ssl_str[$v_domain]['CRT'];
    $v_ssl_key = $ssl_str[$v_domain]['KEY'];
    $v_ssl_ca = $ssl_str[$v_domain]['CA'];
    $v_ssl_subject = $ssl_str[$v_domain]['SUBJECT'];
    $v_ssl_aliases = $ssl_str[$v_domain]['ALIASES'];
    $v_ssl_not_before = $ssl_str[$v_domain]['NOT_BEFORE'];
    $v_ssl_not_after = $ssl_str[$v_domain]['NOT_AFTER'];
    $v_ssl_signature = $ssl_str[$v_domain]['SIGNATURE'];
    $v_ssl_pub_key = $ssl_str[$v_domain]['PUB_KEY'];
    $v_ssl_issuer = $ssl_str[$v_domain]['ISSUER'];
    $v_ssl_forcessl = $data[$v_domain]['SSL_FORCE'];
    $v_ssl_hsts = $data[$v_domain]['SSL_HSTS'];
}
$v_letsencrypt = $data[$v_domain]['LETSENCRYPT'];
if (empty($v_letsencrypt)) $v_letsencrypt = 'no';
$v_ssl_home = $data[$v_domain]['SSL_HOME'];
$v_backend_template = $data[$v_domain]['BACKEND'];
$v_proxy = $data[$v_domain]['PROXY'];
$v_proxy_template = $data[$v_domain]['PROXY'];
$v_proxy_ext = str_replace(',', ', ', $data[$v_domain]['PROXY_EXT']);
$v_stats = $data[$v_domain]['STATS'];
$v_stats_user = $data[$v_domain]['STATS_USER'];
if (!empty($v_stats_user)) $v_stats_password = "";
$v_custom_doc_root_prepath = '/home/'.$v_username.'/web/';
$v_custom_doc_root = $data[$v_domain]['CUSTOM_DOCROOT'];

$m = preg_match('/\/home\/'.$v_username.'\/web\/([A-Za-z0-9.-].*)\/([A-Za-z0-9.-\/].*)/', $v_custom_doc_root, $matches);
$v_custom_doc_domain = $matches[1];
$v_custom_doc_folder = str_replace('public_html/','',$matches[2]);

$v_ftp_user = $data[$v_domain]['FTP_USER'];
$v_ftp_path = $data[$v_domain]['FTP_PATH'];
if (!empty($v_ftp_user)) $v_ftp_password = "";

if($v_custom_doc_domain != ''){
    $v_ftp_user_prepath = '/home/'.$v_username.'/web/'.$v_custom_doc_domain;
}else{
    $v_ftp_user_prepath = '/home/'.$v_username.'/web/'.$v_domain;
}


$v_ftp_email = $panel[$user]['CONTACT'];
$v_suspended = $data[$v_domain]['SUSPENDED'];
if ( $v_suspended == 'yes' ) {
    $v_status =  'suspended';
} else {
    $v_status =  'active';
}
$v_time = $data[$v_domain]['TIME'];
$v_date = $data[$v_domain]['DATE'];

// List ip addresses
exec (HESTIA_CMD."v-list-user-ips ".$user." json", $output, $return_var);
$ips = json_decode(implode('', $output), true);
unset($output);

$v_ip_public = empty($ips[$v_ip]['NAT']) ? $v_ip : $ips[$v_ip]['NAT'];

// List web templates
exec (HESTIA_CMD."v-list-web-templates json", $output, $return_var);
$templates = json_decode(implode('', $output), true);
unset($output);

// List backend templates
if (!empty($_SESSION['WEB_BACKEND'])) {
    exec (HESTIA_CMD."v-list-web-templates-backend json", $output, $return_var);
    $backend_templates = json_decode(implode('', $output), true);
    unset($output);
}

// List proxy templates
if (!empty($_SESSION['PROXY_SYSTEM'])) {
    exec (HESTIA_CMD."v-list-web-templates-proxy json", $output, $return_var);
    $proxy_templates = json_decode(implode('', $output), true);
    unset($output);
}

// List web stat engines
exec (HESTIA_CMD."v-list-web-stats json", $output, $return_var);
$stats = json_decode(implode('', $output), true);
unset($output);

// Check POST request
if (!empty($_POST['save'])) {
    $v_domain = $_POST['v_domain'];
    if(!in_array($v_domain, $user_domains)) {
        check_return_code(3, ["Unknown domain"]);
    }
    // Check token
    if ((!isset($_POST['token'])) || ($_SESSION['token'] != $_POST['token'])) {
        header('location: /login/');
        exit();
    }

    // Change web domain IP
    $v_newip='';
    $v_newip_public='';

    if(!empty($_POST['v_ip'])) {
        $v_newip = $_POST['v_ip'];
        $v_newip_public = empty($ips[$v_newip]['NAT']) ? $v_newip : $ips[$v_newip]['NAT'];
    }

    if (($v_ip != $_POST['v_ip']) && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-change-web-domain-ip ".$v_username." ".escapeshellarg($v_domain)." ".escapeshellarg($_POST['v_ip'])." 'no'", $output, $return_var);
        check_return_code($return_var,$output);
        $restart_web = 'yes';
        $restart_proxy = 'yes';
        unset($output);
    }

    // Change dns domain IP
    if (($v_ip != $_POST['v_ip']) && (empty($_SESSION['error_msg'])))  {
        exec (HESTIA_CMD."v-list-dns-domain ".$v_username." ".escapeshellarg($v_domain)." json", $output, $return_var);
        unset($output);
        if ($return_var == 0 ) {
            exec (HESTIA_CMD."v-change-dns-domain-ip ".$v_username." ".escapeshellarg($v_domain)." ".escapeshellarg($v_newip_public)." 'no'", $output, $return_var);
            check_return_code($return_var,$output);
            unset($output);
            $restart_dns = 'yes';
        }
    }

    // Change dns ip for each alias
    if (($v_ip != $_POST['v_ip']) && (empty($_SESSION['error_msg']))) {
        foreach($valiases as $v_alias ){
            exec (HESTIA_CMD."v-list-dns-domain ".$v_username." ".escapeshellarg($v_alias)." json", $output, $return_var);
            unset($output);
            if ($return_var == 0 ) {
                exec (HESTIA_CMD."v-change-dns-domain-ip ".$v_username." ".escapeshellarg($v_alias)." ".escapeshellarg($v_newip_public), $output, $return_var);
                check_return_code($return_var,$output);
                unset($output);
                $restart_dns = 'yes';
            }
        }
    }

    // Change mail domain IP
    if (($v_ip != $_POST['v_ip']) && (empty($_SESSION['error_msg'])))  {
        exec (HESTIA_CMD."v-list-mail-domain ".$v_username." ".escapeshellarg($v_domain)." json", $output, $return_var);
        unset($output);
        if ($return_var == 0 ) {
            exec (HESTIA_CMD."v-rebuild-mail-domain ".$v_username." ".escapeshellarg($v_domain), $output, $return_var);
            check_return_code($return_var,$output);
            unset($output);
            $restart_email = 'yes';
        }
    }

    // Change template
    if (($v_template != $_POST['v_template']) && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-change-web-domain-tpl ".$v_username." ".escapeshellarg($v_domain)." ".escapeshellarg($_POST['v_template'])." 'no'", $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
        $restart_web = 'yes';
    }

    // Change aliases
    if (empty($_SESSION['error_msg'])) {
        $waliases = preg_replace("/\n/", " ", $_POST['v_aliases']);
        $waliases = preg_replace("/,/", " ", $waliases);
        $waliases = preg_replace('/\s+/', ' ',$waliases);
        $waliases = trim($waliases);
        $aliases = explode(" ", $waliases);
        $v_aliases = str_replace(' ', "\n", $waliases);
        $result = array_diff($valiases, $aliases);
        foreach ($result as $alias) {
            if ((empty($_SESSION['error_msg'])) && (!empty($alias))) {
                $restart_web = 'yes';
                $restart_proxy = 'yes';
                exec (HESTIA_CMD."v-delete-web-domain-alias ".$v_username." ".escapeshellarg($v_domain)." ".escapeshellarg($alias)." 'no'", $output, $return_var);
                check_return_code($return_var,$output);
                unset($output);

                if (empty($_SESSION['error_msg'])) {
                    exec (HESTIA_CMD."v-list-dns-domain ".$v_username." ".escapeshellarg($v_domain), $output, $return_var);
                    unset($output);
                    if ($return_var == 0) {
                        exec (HESTIA_CMD."v-delete-dns-on-web-alias ".$v_username." ".escapeshellarg($v_domain)." ".escapeshellarg($alias)." 'no'", $output, $return_var);
                        check_return_code($return_var,$output);
                        unset($output);
                        $restart_dns = 'yes';
                    }
                }
            }
        }

        $result = array_diff($aliases, $valiases);
        foreach ($result as $alias) {
            if ((empty($_SESSION['error_msg'])) && (!empty($alias))) {
                $restart_web = 'yes';
                $restart_proxy = 'yes';
                exec (HESTIA_CMD."v-add-web-domain-alias ".$v_username." ".escapeshellarg($v_domain)." ".escapeshellarg($alias)." 'no'", $output, $return_var);
                check_return_code($return_var,$output);
                unset($output);
                if (empty($_SESSION['error_msg'])) {
                    exec (HESTIA_CMD."v-list-dns-domain ".$v_username." ".escapeshellarg($v_domain), $output, $return_var);
                    unset($output);
                    if ($return_var == 0) {
                        exec (HESTIA_CMD."v-add-dns-on-web-alias ".$v_username." ".escapeshellarg($alias)." ".escapeshellarg($v_newip_public ?: $v_ip_public)." no", $output, $return_var);
                        check_return_code($return_var,$output);
                    unset($output);
                        $restart_dns = 'yes';
                    }
                }
            }
        }
        if ((!empty($v_stats)) && ($_POST['v_stats'] == $v_stats) && (empty($_SESSION['error_msg']))) {
            // Update statistics configuration when changing domain aliases
            $v_stats = escapeshellarg($_POST['v_stats']);
            exec (HESTIA_CMD."v-change-web-domain-stats ".$v_username." ".escapeshellarg($v_domain)." ".$v_stats, $output, $return_var);
            check_return_code($return_var,$output);
            unset($output);
        }
    }
    
    // Change backend template
    if ((!empty($_SESSION['WEB_BACKEND'])) && ( $v_backend_template != $_POST['v_backend_template']) && ( $_SESSION['user'] == 'admin') && (empty($_SESSION['error_msg']))) {
        $v_backend_template = $_POST['v_backend_template'];
        exec (HESTIA_CMD."v-change-web-domain-backend-tpl ".$v_username." ".escapeshellarg($v_domain)." ".escapeshellarg($v_backend_template), $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
    }

    // Delete proxy support
    if ((!empty($_SESSION['PROXY_SYSTEM'])) && (!empty($v_proxy)) && (empty($_POST['v_proxy'])) && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-delete-web-domain-proxy ".$v_username." ".escapeshellarg($v_domain)." 'no'", $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
        unset($v_proxy);
        $restart_proxy = 'yes';
    }

    // Change proxy template / Update extension list
    if ((!empty($_SESSION['PROXY_SYSTEM'])) && (!empty($v_proxy)) && (!empty($_POST['v_proxy'])) && (empty($_SESSION['error_msg'])) ) {
        $ext = preg_replace("/\n/", " ", $_POST['v_proxy_ext']);
        $ext = preg_replace("/,/", " ", $ext);
        $ext = preg_replace('/\s+/', ' ',$ext);
        $ext = trim($ext);
        $ext = str_replace(' ', ", ", $ext);
        if (( $v_proxy_template != $_POST['v_proxy_template']) || ($v_proxy_ext != $ext)) {
            $ext = str_replace(', ', ",", $ext);
            if (!empty($_POST['v_proxy_template'])) $v_proxy_template = $_POST['v_proxy_template'];
            exec (HESTIA_CMD."v-change-web-domain-proxy-tpl ".$v_username." ".escapeshellarg($v_domain)." ".escapeshellarg($v_proxy_template)." ".escapeshellarg($ext)." 'no'", $output, $return_var);
            check_return_code($return_var,$output);
            $v_proxy_ext = str_replace(',', ', ', $ext);
            unset($output);
            $restart_proxy = 'yes';
        }
    }

    // Add proxy support
    if ((!empty($_SESSION['PROXY_SYSTEM'])) && (empty($v_proxy)) && (!empty($_POST['v_proxy'])) && (empty($_SESSION['error_msg']))) {
        $v_proxy_template = $_POST['v_proxy_template'];
        if (!empty($_POST['v_proxy_ext'])) {
            $ext = preg_replace("/\n/", " ", $_POST['v_proxy_ext']);
            $ext = preg_replace("/,/", " ", $ext);
            $ext = preg_replace('/\s+/', ' ',$ext);
            $ext = trim($ext);
            $ext = str_replace(' ', ",", $ext);
            $v_proxy_ext = str_replace(',', ', ', $ext);
        }
        exec (HESTIA_CMD."v-add-web-domain-proxy ".$v_username." ".escapeshellarg($v_domain)." ".escapeshellarg($v_proxy_template)." ".escapeshellarg($ext)." 'no'", $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
        $restart_proxy = 'yes';
    }

    // Change document root for ssl domain
    if (( $v_ssl == 'yes') && (!empty($_POST['v_ssl'])) && (empty($_SESSION['error_msg']))) {
        if ( $v_ssl_home != $_POST['v_ssl_home'] ) {
            $v_ssl_home = escapeshellarg($_POST['v_ssl_home']);
            exec (HESTIA_CMD."v-change-web-domain-sslhome ".$user." ".escapeshellarg($v_domain)." ".$v_ssl_home." 'no'", $output, $return_var);
            check_return_code($return_var,$output);
            $v_ssl_home = $_POST['v_ssl_home'];
            $restart_web = 'yes';
            $restart_proxy = 'yes';
            unset($output);
        }
    }

    // Change SSL certificate
    if (( $v_letsencrypt == 'no' ) && (empty($_POST['v_letsencrypt'])) && ( $v_ssl == 'yes' ) && (!empty($_POST['v_ssl'])) && (empty($_SESSION['error_msg']))) {
        if (( $v_ssl_crt != str_replace("\r\n", "\n",  $_POST['v_ssl_crt'])) || ( $v_ssl_key != str_replace("\r\n", "\n",  $_POST['v_ssl_key'])) || ( $v_ssl_ca != str_replace("\r\n", "\n",  $_POST['v_ssl_ca']))) {
            exec ('mktemp -d', $mktemp_output, $return_var);
            $tmpdir = $mktemp_output[0];

            // Certificate
            if (!empty($_POST['v_ssl_crt'])) {
                $fp = fopen($tmpdir."/".$v_domain.".crt", 'w');
                fwrite($fp, str_replace("\r\n", "\n",  $_POST['v_ssl_crt']));
                fwrite($fp, "\n");
                fclose($fp);
            }

            // Key
            if (!empty($_POST['v_ssl_key'])) {
                $fp = fopen($tmpdir."/".$v_domain.".key", 'w');
                fwrite($fp, str_replace("\r\n", "\n", $_POST['v_ssl_key']));
                fwrite($fp, "\n");
                fclose($fp);
            }

            // CA
            if (!empty($_POST['v_ssl_ca'])) {
                $fp = fopen($tmpdir."/".$v_domain.".ca", 'w');
                fwrite($fp, str_replace("\r\n", "\n", $_POST['v_ssl_ca']));
                fwrite($fp, "\n");
                fclose($fp);
            }

            exec (HESTIA_CMD."v-change-web-domain-sslcert ".$user." ".escapeshellarg($v_domain)." ".$tmpdir." 'no'", $output, $return_var);
            check_return_code($return_var,$output);
            unset($output);
            $restart_web = 'yes';
            $restart_proxy = 'yes';

            exec (HESTIA_CMD."v-list-web-domain-ssl ".$user." ".escapeshellarg($v_domain)." json", $output, $return_var);
            $ssl_str = json_decode(implode('', $output), true);
            unset($output);
            $v_ssl_crt = $ssl_str[$v_domain]['CRT'];
            $v_ssl_key = $ssl_str[$v_domain]['KEY'];
            $v_ssl_ca = $ssl_str[$v_domain]['CA'];
            $v_ssl_subject = $ssl_str[$v_domain]['SUBJECT'];
            $v_ssl_aliases = $ssl_str[$v_domain]['ALIASES'];
            $v_ssl_not_before = $ssl_str[$v_domain]['NOT_BEFORE'];
            $v_ssl_not_after = $ssl_str[$v_domain]['NOT_AFTER'];
            $v_ssl_signature = $ssl_str[$v_domain]['SIGNATURE'];
            $v_ssl_pub_key = $ssl_str[$v_domain]['PUB_KEY'];
            $v_ssl_issuer = $ssl_str[$v_domain]['ISSUER'];

            // Cleanup certificate tempfiles
            if (!empty($_POST['v_ssl_crt'])) unlink($tmpdir."/".$v_domain.".crt");
            if (!empty($_POST['v_ssl_key'])) unlink($tmpdir."/".$v_domain.".key");
            if (!empty($_POST['v_ssl_ca']))  unlink($tmpdir."/".$v_domain.".ca");
            rmdir($tmpdir);
        }
    }

    // Delete Lets Encrypt support
    if (( $v_letsencrypt == 'yes' ) && (empty($_POST['v_letsencrypt']) || empty($_POST['v_ssl'])) && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-delete-letsencrypt-domain ".$user." ".escapeshellarg($v_domain)." ''", $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
        $v_ssl_crt = '';
        $v_ssl_key = '';
        $v_ssl_ca = '';
        $v_letsencrypt = 'no';
        $v_letsencrypt_deleted = 'yes';
        $v_ssl = 'no';
        $restart_web = 'yes';
        $restart_proxy = 'yes';
    }

    // Delete SSL certificate
    if (( $v_ssl == 'yes' ) && (empty($_POST['v_ssl'])) && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-delete-web-domain-ssl ".$v_username." ".escapeshellarg($v_domain)." 'no'", $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
        $v_ssl_crt = '';
        $v_ssl_key = '';
        $v_ssl_ca = '';
        $v_ssl = 'no';
        $v_ssl_forcessl = 'no';
        $v_ssl_hsts = 'no';
        $restart_web = 'yes';
        $restart_proxy = 'yes';
    }

    // Add Lets Encrypt support
    if ((!empty($_POST['v_ssl'])) && ( $v_letsencrypt == 'no' ) && (!empty($_POST['v_letsencrypt'])) && empty($_SESSION['error_msg'])) {
        $l_aliases = str_replace("\n", ',', $v_aliases);
        exec (HESTIA_CMD."v-add-letsencrypt-domain ".$user." ".escapeshellarg($v_domain)." ".escapeshellarg($l_aliases)." ''", $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
        $v_letsencrypt = 'yes';
        $v_ssl = 'yes';
        $restart_web = 'yes';
        $restart_proxy = 'yes';
     }

     // Add SSL certificate
     if (( $v_ssl == 'no' ) && (!empty($_POST['v_ssl']))  && (empty($v_letsencrypt_deleted)) && (empty($_SESSION['error_msg']))) {
        if (empty($_POST['v_ssl_crt'])) $errors[] = 'ssl certificate';
        if (empty($_POST['v_ssl_key'])) $errors[] = 'ssl key';
        if (empty($_POST['v_ssl_home'])) $errors[] = 'ssl home';
        $v_ssl_home = escapeshellarg($_POST['v_ssl_home']);
        if (!empty($errors[0])) {
            foreach ($errors as $i => $error) {
                if ( $i == 0 ) {
                    $error_msg = $error;
                } else {
                    $error_msg = $error_msg.", ".$error;
                }
            }
            $_SESSION['error_msg'] = __('Field "%s" can not be blank.',$error_msg);
        } else {
            exec ('mktemp -d', $mktemp_output, $return_var);
            $tmpdir = $mktemp_output[0];

            // Certificate
            if (!empty($_POST['v_ssl_crt'])) {
                $fp = fopen($tmpdir."/".$v_domain.".crt", 'w');
                fwrite($fp, str_replace("\r\n", "\n", $_POST['v_ssl_crt']));
                fclose($fp);
            }

            // Key
            if (!empty($_POST['v_ssl_key'])) {
                $fp = fopen($tmpdir."/".$v_domain.".key", 'w');
                fwrite($fp, str_replace("\r\n", "\n", $_POST['v_ssl_key']));
                fclose($fp);
            }

            // CA
            if (!empty($_POST['v_ssl_ca'])) {
                $fp = fopen($tmpdir."/".$v_domain.".ca", 'w');
                fwrite($fp, str_replace("\r\n", "\n", $_POST['v_ssl_ca']));
                fclose($fp);
            }
            exec (HESTIA_CMD."v-add-web-domain-ssl ".$user." ".escapeshellarg($v_domain)." ".$tmpdir." ".$v_ssl_home." 'no'", $output, $return_var);
            check_return_code($return_var,$output);
            unset($output);
            $v_ssl = 'yes';
            $restart_web = 'yes';
            $restart_proxy = 'yes';

            exec (HESTIA_CMD."v-list-web-domain-ssl ".$user." ".escapeshellarg($v_domain)." json", $output, $return_var);
            $ssl_str = json_decode(implode('', $output), true);
            unset($output);
            $v_ssl_crt = $ssl_str[$v_domain]['CRT'];
            $v_ssl_key = $ssl_str[$v_domain]['KEY'];
            $v_ssl_ca = $ssl_str[$v_domain]['CA'];
            $v_ssl_subject = $ssl_str[$v_domain]['SUBJECT'];
            $v_ssl_aliases = $ssl_str[$v_domain]['ALIASES'];
            $v_ssl_not_before = $ssl_str[$v_domain]['NOT_BEFORE'];
            $v_ssl_not_after = $ssl_str[$v_domain]['NOT_AFTER'];
            $v_ssl_signature = $ssl_str[$v_domain]['SIGNATURE'];
            $v_ssl_pub_key = $ssl_str[$v_domain]['PUB_KEY'];
            $v_ssl_issuer = $ssl_str[$v_domain]['ISSUER'];

            // Cleanup certificate tempfiles
            if (!empty($_POST['v_ssl_crt'])) unlink($tmpdir."/".$v_domain.".crt");
            if (!empty($_POST['v_ssl_key'])) unlink($tmpdir."/".$v_domain.".key");
            if (!empty($_POST['v_ssl_ca'])) unlink($tmpdir."/".$v_domain.".ca");
            rmdir($tmpdir);
        }
    }
    
    // Add Force SSL
    if ((!empty($_POST['v_ssl_forcessl'])) && (!empty($_POST['v_ssl'])) && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-add-web-domain-ssl-force ".$user." ".escapeshellarg($v_domain), $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
        $v_ssl_forcessl = 'yes';
    }

    // Add SSL HSTS
    if ((!empty($_POST['v_ssl_hsts'])) && (!empty($_POST['v_ssl'])) && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-add-web-domain-ssl-hsts ".$user." ".escapeshellarg($v_domain), $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
        $v_ssl_hsts = 'yes';
    }
    
    // Delete Force SSL
    if (( $v_ssl_forcessl == 'yes' ) && (empty($_POST['v_ssl_forcessl'])) && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-delete-web-domain-ssl-force ".$user." ".escapeshellarg($v_domain)." yes", $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
        $v_ssl_forcessl = 'no';
    }

    // Delete SSL HSTS
    if (( $v_ssl_hsts == 'yes' ) && (empty($_POST['v_ssl_hsts'])) && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-delete-web-domain-ssl-hsts ".$user." ".escapeshellarg($v_domain)." yes", $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
        $v_ssl_hsts = 'no';
    }

    // Delete web stats
    if ((!empty($v_stats)) && ($_POST['v_stats'] == 'none') && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-delete-web-domain-stats ".$v_username." ".escapeshellarg($v_domain), $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
        $v_stats = '';
    }

    // Change web stats engine
    if ((!empty($v_stats)) && ($_POST['v_stats'] != $v_stats) && (empty($_SESSION['error_msg']))) {
        $v_stats = escapeshellarg($_POST['v_stats']);
        exec (HESTIA_CMD."v-change-web-domain-stats ".$v_username." ".escapeshellarg($v_domain)." ".$v_stats, $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
    }

    // Add web stats
    if ((empty($v_stats)) && ($_POST['v_stats'] != 'none') && (empty($_SESSION['error_msg']))) {
        $v_stats = escapeshellarg($_POST['v_stats']);
        exec (HESTIA_CMD."v-add-web-domain-stats ".$v_username." ".escapeshellarg($v_domain)." ".$v_stats, $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
    }

    // Delete web stats authorization
    if ((!empty($v_stats_user)) && (empty($_POST['v_stats_auth'])) && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-delete-web-domain-stats-user ".$v_username." ".escapeshellarg($v_domain), $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
        $v_stats_user = '';
        $v_stats_password = '';
    }

    // Change web stats user or password
    if ((empty($v_stats_user)) && (!empty($_POST['v_stats_auth'])) && (empty($_SESSION['error_msg']))) {
        if (empty($_POST['v_stats_user'])) $errors[] = __('stats username');
        if (!empty($errors[0])) {
            foreach ($errors as $i => $error) {
                if ( $i == 0 ) {
                    $error_msg = $error;
                } else {
                    $error_msg = $error_msg.", ".$error;
                }
            }
            $_SESSION['error_msg'] = __('Field "%s" can not be blank.',$error_msg);
        } else {
            $v_stats_user = escapeshellarg($_POST['v_stats_user']);
            $v_stats_password = tempnam("/tmp","vst");
            $fp = fopen($v_stats_password, "w");
            fwrite($fp, $_POST['v_stats_password']."\n");
            fclose($fp);
            exec (HESTIA_CMD."v-add-web-domain-stats-user ".$v_username." ".escapeshellarg($v_domain)." ".$v_stats_user." ".$v_stats_password, $output, $return_var);
            check_return_code($return_var,$output);
            unset($output);
            unlink($v_stats_password);
            $v_stats_password = escapeshellarg($_POST['v_stats_password']);
        }
    }

    // Add web stats authorization
    if ((!empty($v_stats_user)) && (!empty($_POST['v_stats_auth'])) && (empty($_SESSION['error_msg']))) {
        if (empty($_POST['v_stats_user'])) $errors[] = __('stats user');
        if (!empty($errors[0])) {
            foreach ($errors as $i => $error) {
                if ( $i == 0 ) {
                    $error_msg = $error;
                } else {
                    $error_msg = $error_msg.", ".$error;
                }
            }
            $_SESSION['error_msg'] = __('Field "%s" can not be blank.',$error_msg);
        }
        if (($v_stats_user != $_POST['v_stats_user']) || (!empty($_POST['v_stats_password'])) && (empty($_SESSION['error_msg']))) {
            $v_stats_user = escapeshellarg($_POST['v_stats_user']);
            $v_stats_password = tempnam("/tmp","vst");
            $fp = fopen($v_stats_password, "w");
            fwrite($fp, $_POST['v_stats_password']."\n");
            fclose($fp);
            exec (HESTIA_CMD."v-add-web-domain-stats-user ".$v_username." ".escapeshellarg($v_domain)." ".$v_stats_user." ".$v_stats_password, $output, $return_var);
            check_return_code($return_var,$output);
            unset($output);
            unlink($v_stats_password);
            $v_stats_password = escapeshellarg($_POST['v_stats_password']);
        }
    }

    // Update ftp account
    if (!empty($_POST['v_ftp_user'])) {
        $v_ftp_users_updated = array();
        foreach ($_POST['v_ftp_user'] as $i => $v_ftp_user_data) {
            if (empty($v_ftp_user_data['v_ftp_user'])) {
                continue;
            }

            $v_ftp_user_data['v_ftp_user'] = preg_replace("/^".$user."_/i", "", $v_ftp_user_data['v_ftp_user']);
            if ($v_ftp_user_data['is_new'] == 1 && !empty($_POST['v_ftp'])) {
                if ((!empty($v_ftp_user_data['v_ftp_email'])) && (!filter_var($v_ftp_user_data['v_ftp_email'], FILTER_VALIDATE_EMAIL))) $_SESSION['error_msg'] = __('Please enter valid email address.');
                if (empty($v_ftp_user_data['v_ftp_user'])) $errors[] = 'ftp user';
                if (!empty($errors[0])) {
                    foreach ($errors as $i => $error) {
                        if ( $i == 0 ) {
                            $error_msg = $error;
                        } else {
                            $error_msg = $error_msg.", ".$error;
                        }
                    }
                    $_SESSION['error_msg'] = __('Field "%s" can not be blank.',$error_msg);
                }

                // Add ftp account
                $v_ftp_username      = $v_ftp_user_data['v_ftp_user'];
                $v_ftp_username_full = $user . '_' . $v_ftp_user_data['v_ftp_user'];
                $v_ftp_user = escapeshellarg($v_ftp_username);
                $v_ftp_path = escapeshellarg(trim($v_ftp_user_data['v_ftp_path']));
                if (empty($_SESSION['error_msg'])) {
                    $v_ftp_password = tempnam("/tmp","vst");
                    $fp = fopen($v_ftp_password, "w");
                    fwrite($fp, $v_ftp_user_data['v_ftp_password']."\n");
                    fclose($fp);
                    exec (HESTIA_CMD."v-add-web-domain-ftp ".$v_username." ".escapeshellarg($v_domain)." ".$v_ftp_user." ".$v_ftp_password . " " . $v_ftp_path, $output, $return_var);
                    check_return_code($return_var,$output);
                    if ((!empty($v_ftp_user_data['v_ftp_email'])) && (empty($_SESSION['error_msg']))) {
                        $to = $v_ftp_user_data['v_ftp_email'];
                        $subject = __("FTP login credentials");
                        $hostname = exec('hostname');
                        $from = __('MAIL_FROM',$hostname);
                        $mailtext = __('FTP_ACCOUNT_READY',escapeshellarg($_GET['domain']),$user,$v_ftp_username,$v_ftp_user_data['v_ftp_password']);
                        send_email($to, $subject, $mailtext, $from);
                        unset($v_ftp_email);
                    }
                    unset($output);
                    unlink($v_ftp_password);
                    $v_ftp_password = escapeshellarg($v_ftp_user_data['v_ftp_password']);
                }

                if ($return_var == 0) {
                    $v_ftp_password = "";
                    $v_ftp_user_data['is_new'] = 0;
                }
                else {
                    $v_ftp_user_data['is_new'] = 1;
                }

                $v_ftp_users_updated[] = array(
                    'is_new'            => empty($_SESSION['error_msg']) ? 0 : 1,
                    'v_ftp_user'        => $v_ftp_username_full,
                    'v_ftp_password'    => $v_ftp_password,
                    'v_ftp_path'        => $v_ftp_user_data['v_ftp_path'],
                    'v_ftp_email'       => $v_ftp_user_data['v_ftp_email'],
                    'v_ftp_pre_path'    => $v_ftp_user_prepath
                );

                continue;
            }

            // Delete FTP account
            if ($v_ftp_user_data['delete'] == 1) {
                $v_ftp_username = $user . '_' . $v_ftp_user_data['v_ftp_user'];
                exec (HESTIA_CMD."v-delete-web-domain-ftp ".$v_username." ".escapeshellarg($v_domain)." ".$v_ftp_username, $output, $return_var);
                check_return_code($return_var,$output);
                unset($output);

                continue;
            }

            if (!empty($_POST['v_ftp'])) {
                if (empty($v_ftp_user_data['v_ftp_user'])) $errors[] = __('ftp user');
                if (!empty($errors[0])) {
                    foreach ($errors as $i => $error) {
                        if ( $i == 0 ) {
                            $error_msg = $error;
                        } else {
                            $error_msg = $error_msg.", ".$error;
                        }
                    }
                    $_SESSION['error_msg'] = __('Field "%s" can not be blank.',$error_msg);
                }

                // Change FTP account path
                $v_ftp_username_for_emailing = $v_ftp_user_data['v_ftp_user'];
                $v_ftp_username = $user . '_' . $v_ftp_user_data['v_ftp_user']; //preg_replace("/^".$user."_/", "", $v_ftp_user_data['v_ftp_user']);
                $v_ftp_username = escapeshellarg($v_ftp_username);
                    $v_ftp_path = escapeshellarg(trim($v_ftp_user_data['v_ftp_path']));
                    if(escapeshellarg(trim($v_ftp_user_data['v_ftp_path_prev'])) != $v_ftp_path) {
                        exec (HESTIA_CMD."v-change-web-domain-ftp-path ".$v_username." ".escapeshellarg($v_domain)." ".$v_ftp_username." ".$v_ftp_path, $output, $return_var);
                    }

                // Change FTP account password
                if (!empty($v_ftp_user_data['v_ftp_password'])) {
                    $v_ftp_password = tempnam("/tmp","vst");
                    $fp = fopen($v_ftp_password, "w");
                    fwrite($fp, $v_ftp_user_data['v_ftp_password']."\n");
                    fclose($fp);
                    exec (HESTIA_CMD."v-change-web-domain-ftp-password ".$v_username." ".escapeshellarg($v_domain)." ".$v_ftp_username." ".$v_ftp_password, $output, $return_var);
                    unlink($v_ftp_password);

                    $to = $v_ftp_user_data['v_ftp_email'];
                    $subject = __("FTP login credentials");
                    $hostname = exec('hostname');
                    $from = __('MAIL_FROM',$hostname);
                    $mailtext = __('FTP_ACCOUNT_READY',escapeshellarg($_GET['domain']),$user,$v_ftp_username_for_emailing,$v_ftp_user_data['v_ftp_password']);
                    send_email($to, $subject, $mailtext, $from);
                    unset($v_ftp_email);
                }
                check_return_code($return_var, $output);
                unset($output);

                $v_ftp_users_updated[] = array(
                    'is_new'            => 0,
                    'v_ftp_user'        => $v_ftp_username,
                    'v_ftp_password'    => $v_ftp_user_data['v_ftp_password'],
                    'v_ftp_path'        => $v_ftp_user_data['v_ftp_path'],
                    'v_ftp_email'       => $v_ftp_user_data['v_ftp_email'],
                    'v_ftp_pre_path'    => $v_ftp_user_prepath
                );
            }
        }
    }
    //custom docoot with check box disabled      
    if( !empty($v_custom_doc_root) && empty($_POST['v_custom_doc_root_check']) ){
        exec(HESTIA_CMD."v-change-web-domain-docroot ".$v_username." ".escapeshellarg($v_domain)." default",  $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);    
        unset($_POST['v-custom-doc-domain'], $_POST['v-custom-doc-folder']);    
    }

    if ( !empty($_POST['v-custom-doc-domain']) && !empty($_POST['v_custom_doc_root_check']) && $v_custom_doc_root_prepath.$v_custom_doc_domain.'/public_html'.$v_custom_doc_folder != $v_custom_doc_root || ($_POST['v-custom-doc-domain'] == $v_domain && !empty($_POST['v-custom-doc-folder']))){
        
        $v_custom_doc_domain = escapeshellarg($_POST['v-custom-doc-domain']);
        $v_custom_doc_folder = escapeshellarg($_POST['v-custom-doc-folder']);
        
        exec(HESTIA_CMD."v-change-web-domain-docroot ".$v_username." ".escapeshellarg($v_domain)." ".$v_custom_doc_domain." ".$v_custom_doc_folder,  $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);  
        $v_custom_doc_root = 1;        
        
        
    }    
    

    // Restart web server
    if (!empty($restart_web) && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-restart-web", $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
    }

    // Restart proxy server
    if ((!empty($_SESSION['PROXY_SYSTEM'])) && !empty($restart_proxy) && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-restart-proxy", $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
    }

    // Restart dns server
    if (!empty($restart_dns) && (empty($_SESSION['error_msg']))) {
        exec (HESTIA_CMD."v-restart-dns", $output, $return_var);
        check_return_code($return_var,$output);
        unset($output);
    }

    // Set success message
    if (empty($_SESSION['error_msg'])) {
        $_SESSION['ok_msg'] = __('Changes has been saved.');
        header("Location: /edit/web/?domain=" . $v_domain);
    }

}


$v_ftp_users_raw = explode(':', $v_ftp_user);
$v_ftp_users_paths_raw = explode(':', $data[$v_domain]['FTP_PATH']);
$v_ftp_users = array();
foreach ($v_ftp_users_raw as $v_ftp_user_index => $v_ftp_user_val) {
    if (empty($v_ftp_user_val)) {
        continue;
    }
    $v_ftp_users[] = array(
        'is_new'            => 0,
        'v_ftp_user'        => $v_ftp_user_val,
        'v_ftp_password'    => $v_ftp_password,
        'v_ftp_path'        => (isset($v_ftp_users_paths_raw[$v_ftp_user_index]) ? $v_ftp_users_paths_raw[$v_ftp_user_index] : ''),
        'v_ftp_email'       => $v_ftp_email,
        'v_ftp_pre_path'    => $v_ftp_user_prepath
    );
}

if (empty($v_ftp_users)) {
    $v_ftp_user = null;
    $v_ftp_users[] = array(
        'is_new'            => 1,
        'v_ftp_user'        => '',
        'v_ftp_password'    => '',
        'v_ftp_path'        => (isset($v_ftp_users_paths_raw[$v_ftp_user_index]) ? $v_ftp_users_paths_raw[$v_ftp_user_index] : ''),
        'v_ftp_email'       => '',
        'v_ftp_pre_path'    => $v_ftp_user_prepath
    );
}

// set default pre path for newly created users
$v_ftp_pre_path_new_user = $v_ftp_user_prepath;
if (isset($v_ftp_users_updated)) {
    $v_ftp_users = $v_ftp_users_updated;
    if (empty($v_ftp_users_updated)) {
        $v_ftp_user = null;
        $v_ftp_users[] = array(
            'is_new'            => 1,
            'v_ftp_user'        => '',
            'v_ftp_password'    => '',
            'v_ftp_path'        => (isset($v_ftp_users_paths_raw[$v_ftp_user_index]) ? $v_ftp_users_paths_raw[$v_ftp_user_index] : ''),
            'v_ftp_email'       => '',
            'v_ftp_pre_path'    => $v_ftp_user_prepath
        );
    }
}

// Render page
render_page($user, $TAB, 'edit_web');

// Flush session messages
unset($_SESSION['error_msg']);
unset($_SESSION['ok_msg']);
