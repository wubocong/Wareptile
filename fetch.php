<?php
$baseDomain = rtrim($_POST['domain'], '/');
if (!preg_match("/^(http:\/\/|https:\/\/|\/\/)/", $baseDomain)) {
    $baseDomain = "http://" . $baseDomain;
}
$warrior = new reptile($baseDomain);
$warrior->fetch($baseDomain);
class reptile
{
    private $baseDomain, $pdo;
    public function __construct($baseDomain)
    {
        $this->baseDomain = $baseDomain;
        try {
            $this->pdo = new PDO("mysql:host=localhost;port=3306;dbname=reptile", "root", "");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->query("SET NAMES UTF8");
        } catch (PDOException $e) {
            echo '连接数据库失败' . $e->getMessage();
            exit(1);
        }
    }
    private function simulateHttp($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 100);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, sdch');
        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8', 'Origin: ' . $url));
        $content = curl_exec($ch);
        $info    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$content, $info];
    }
    public function fetch($baseDomain)
    {
        $urlArray           = array();
        $count              = 0;
        $i                  = 0;
        $urlArray[$count++] = array($baseDomain, true);

        //第一个参数为url，第二个是否需要追踪（外部链接和非文本资源不追踪）
        while ($i < $count) {
            $domain = $urlArray[$i][0];
            echo "domain: " . $domain . "<br/>";
            if ($urlArray[$i][1]) {
                $css         = false;
                $return      = $this->simulateHttp($domain);
                $fileContent = $return[0];
                // $info = $return[1];
            }
            if (preg_match('/\.css$/', $domain)) {
                $css = true;
            }

            // (url:\s*[\"\'])([^\s\"\']+)[\"\']|(open\([\"\'])([^\s\"\'\)]+)[\"\']

            // echo "<br/><br/><br/>filecontent:<br/>###<br/>".htmlspecialchars($fileContent)."<br/>###<br/><br/><br/>";
            $pos = strrpos($domain, "/");
            if ($pos < strlen($domain) - 1 && preg_match_all('/\//', $domain) > 2) {
                $domain = substr($domain, 0, $pos + 1);
            }

            // echo 'root: ' . $domain . '<br/><br/>';
            if ($css) {
                preg_match_all('/(url\([\"\']?)([^\s\"\'\)]+)[\"\']?\)/', $fileContent, $subStringArray, PREG_SET_ORDER);
            } else {
                preg_match_all('/(href|src)=[\"\']([^\s\"\']+)[\"\']/', $fileContent, $subStringArray, PREG_SET_ORDER);
            }
            foreach ($subStringArray as $matchArray) {
                $url = explode('?', $matchArray[2])[0];
                if ($url != null && !preg_match('/^(javascript:|data:|mailto:)/i', $url)) {
                    $sign = true;
                    if (preg_match('/(\.(eot|woff|svg|ttf|jpe?g|png|gif|bmp)$|#)/', $url)) {
                        $sign = false;
                    }

                    if ($this->stringAssess($url)) {
                        if (preg_match('/^\//', $url)) {
                            $urlCache = $this->baseDomain . $url;
                            if ($this->isNotExisted($urlArray, $urlCache)) {
                                $urlArray[$count] = array($urlCache, $sign);
                                $count++;
                            }
                        } else {
                            $domainCache = $domain;
                            if (preg_match('/^(\.\.\/?)/', $url)) {
                                if (strcmp(rtrim($domainCache, '/'), rtrim($this->baseDomain, '/'))) {

                                    //根域名时不能返回上级
                                    $domainCache = dirname($domainCache);
                                }
                                $url = preg_replace('/(\.\.\/?)/', '', $url);
                            }
                            $url = preg_replace('/(\.\/)/', '', $url);

                            //去除末尾斜杠
                            $urlCache = rtrim($domainCache, '/') . '/' . $url;
                            if ($this->isNotExisted($urlArray, $urlCache)) {
                                $urlArray[$count] = array($urlCache, $sign);
                                $count++;
                            }
                        }
                    } else {
                        $sign = false;
                        if ($this->isNotExisted($urlArray, $url)) {
                            $urlArray[$count] = array($url, $sign);
                            $count++;
                        }
                    }
                }
            }
        }
        $i++;
    }
}
function stringAssess($string)
{
    if (preg_match("/^(https:\/\/|http:\/\/|\/\/)/", $string)) {
        return false;
    } else {
        return true;
    }

}

function isNotExisted($urlArray, $urlCache)
{
    foreach ($urlArray as $string) {
        if (strcasecmp($string[0], $urlCache) == 0) {
            return false;
        }

    }
    return true;
}
}
