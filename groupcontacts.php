<?php

namespace Hp;

//  PROJECT HONEY POT ADDRESS DISTRIBUTION SCRIPT
//  For more information visit: http://www.projecthoneypot.org/
//  Copyright (C) 2004-2020, Unspam Technologies, Inc.
//
//  This program is free software; you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation; either version 2 of the License, or
//  (at your option) any later version.
//
//  This program is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with this program; if not, write to the Free Software
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA
//  02111-1307  USA
//
//  If you choose to modify or redistribute the software, you must
//  completely disconnect it from the Project Honey Pot Service, as
//  specified under the Terms of Service Use. These terms are available
//  here:
//
//  http://www.projecthoneypot.org/terms_of_service_use.php
//
//  The required modification to disconnect the software from the
//  Project Honey Pot Service is explained in the comments below. To find the
//  instructions, search for:  *** DISCONNECT INSTRUCTIONS ***
//
//  Generated On: Wed, 15 Jul 2020 22:09:27 -0400
//  For Domain: bitcoincashstandards.org
//
//

//  *** DISCONNECT INSTRUCTIONS ***
//
//  You are free to modify or redistribute this software. However, if
//  you do so you must disconnect it from the Project Honey Pot Service.
//  To do this, you must delete the lines of code below located between the
//  *** START CUT HERE *** and *** FINISH CUT HERE *** comments. Under the
//  Terms of Service Use that you agreed to before downloading this software,
//  you may not recreate the deleted lines or modify this software to access
//  or otherwise connect to any Project Honey Pot server.
//
//  *** START CUT HERE ***

define('__REQUEST_HOST', 'hpr8.projecthoneypot.org');
define('__REQUEST_PORT', '80');
define('__REQUEST_SCRIPT', '/cgi/serve.php');

//  *** FINISH CUT HERE ***

interface Response
{
    public function getBody();
    public function getLines(): array;
}

class TextResponse implements Response
{
    private $content;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function getBody()
    {
        return $this->content;
    }

    public function getLines(): array
    {
        return explode("\n", $this->content);
    }
}

interface HttpClient
{
    public function request(string $method, string $url, array $headers = [], array $data = []): Response;
}

class ScriptClient implements HttpClient
{
    private $proxy;
    private $credentials;

    public function __construct(string $settings)
    {
        $this->readSettings($settings);
    }

    private function getAuthorityComponent(string $authority = null, string $tag = null)
    {
        if(is_null($authority)){
            return null;
        }
        if(!is_null($tag)){
            $authority .= ":$tag";
        }
        return $authority;
    }

    private function readSettings(string $file)
    {
        if(!is_file($file) || !is_readable($file)){
            return;
        }

        $stmts = file($file);

        $settings = array_reduce($stmts, function($c, $stmt){
            list($key, $val) = \array_pad(array_map('trim', explode(':', $stmt)), 2, null);
            $c[$key] = $val;
            return $c;
        }, []);

        $this->proxy       = $this->getAuthorityComponent($settings['proxy_host'], $settings['proxy_port']);
        $this->credentials = $this->getAuthorityComponent($settings['proxy_user'], $settings['proxy_pass']);
    }

    public function request(string $method, string $uri, array $headers = [], array $data = []): Response
    {
        $options = [
            'http' => [
                'method' => strtoupper($method),
                'header' => $headers + [$this->credentials ? 'Proxy-Authorization: Basic ' . base64_encode($this->credentials) : null],
                'proxy' => $this->proxy,
                'content' => http_build_query($data),
            ],
        ];

        $context = stream_context_create($options);
        $body = file_get_contents($uri, false, $context);

        if($body === false){
            trigger_error(
                "Unable to contact the Server. Are outbound connections disabled? " .
                "(If a proxy is required for outbound traffic, you may configure " .
                "the honey pot to use a proxy. For instructions, visit " .
                "http://www.projecthoneypot.org/settings_help.php)",
                E_USER_ERROR
            );
        }

        return new TextResponse($body);
    }
}

trait AliasingTrait
{
    private $aliases = [];

    public function searchAliases($search, array $aliases, array $collector = [], $parent = null): array
    {
        foreach($aliases as $alias => $value){
            if(is_array($value)){
                return $this->searchAliases($search, $value, $collector, $alias);
            }
            if($search === $value){
                $collector[] = $parent ?? $alias;
            }
        }

        return $collector;
    }

    public function getAliases($search): array
    {
        $aliases = $this->searchAliases($search, $this->aliases);
    
        return !empty($aliases) ? $aliases : [$search];
    }

    public function aliasMatch($alias, $key)
    {
        return $key === $alias;
    }

    public function setAlias($key, $alias)
    {
        $this->aliases[$alias] = $key;
    }

    public function setAliases(array $array)
    {
        array_walk($array, function($v, $k){
            $this->aliases[$k] = $v;
        });
    }
}

abstract class Data
{
    protected $key;
    protected $value;

    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    public function key()
    {
        return $this->key;
    }

    public function value()
    {
        return $this->value;
    }
}

class DataCollection
{
    use AliasingTrait;

    private $data;

    public function __construct(Data ...$data)
    {
        $this->data = $data;
    }

    public function set(Data ...$data)
    {
        array_map(function(Data $data){
            $index = $this->getIndexByKey($data->key());
            if(is_null($index)){
                $this->data[] = $data;
            } else {
                $this->data[$index] = $data;
            }
        }, $data);
    }

    public function getByKey($key)
    {
        $key = $this->getIndexByKey($key);
        return !is_null($key) ? $this->data[$key] : null;
    }

    public function getValueByKey($key)
    {
        $data = $this->getByKey($key);
        return !is_null($data) ? $data->value() : null;
    }

    private function getIndexByKey($key)
    {
        $result = [];
        array_walk($this->data, function(Data $data, $index) use ($key, &$result){
            if($data->key() == $key){
                $result[] = $index;
            }
        });

        return !empty($result) ? reset($result) : null;
    }
}

interface Transcriber
{
    public function transcribe(array $data): DataCollection;
    public function canTranscribe($value): bool;
}

class StringData extends Data
{
    public function __construct($key, string $value)
    {
        parent::__construct($key, $value);
    }
}

class CompressedData extends Data
{
    public function __construct($key, string $value)
    {
        parent::__construct($key, $value);
    }

    public function value()
    {
        $url_decoded = base64_decode(str_replace(['-','_'],['+','/'],$this->value));
        if(substr(bin2hex($url_decoded), 0, 6) === '1f8b08'){
            return gzdecode($url_decoded);
        } else {
            return $this->value;
        }
    }
}

class FlagData extends Data
{
    private $data;

    public function setData($data)
    {
        $this->data = $data;
    }

    public function value()
    {
        return $this->value ? ($this->data ?? null) : null;
    }
}

class CallbackData extends Data
{
    private $arguments = [];

    public function __construct($key, callable $value)
    {
        parent::__construct($key, $value);
    }

    public function setArgument($pos, $param)
    {
        $this->arguments[$pos] = $param;
    }

    public function value()
    {
        ksort($this->arguments);
        return \call_user_func_array($this->value, $this->arguments);
    }
}

class DataFactory
{
    private $data;
    private $callbacks;

    private function setData(array $data, string $class, DataCollection $dc = null)
    {
        $dc = $dc ?? new DataCollection;
        array_walk($data, function($value, $key) use($dc, $class){
            $dc->set(new $class($key, $value));
        });
        return $dc;
    }

    public function setStaticData(array $data)
    {
        $this->data = $this->setData($data, StringData::class, $this->data);
    }

    public function setCompressedData(array $data)
    {
        $this->data = $this->setData($data, CompressedData::class, $this->data);
    }

    public function setCallbackData(array $data)
    {
        $this->callbacks = $this->setData($data, CallbackData::class, $this->callbacks);
    }

    public function fromSourceKey($sourceKey, $key, $value)
    {
        $keys = $this->data->getAliases($key);
        $key = reset($keys);
        $data = $this->data->getValueByKey($key);

        switch($sourceKey){
            case 'directives':
                $flag = new FlagData($key, $value);
                if(!is_null($data)){
                    $flag->setData($data);
                }
                return $flag;
            case 'email':
            case 'emailmethod':
                $callback = $this->callbacks->getByKey($key);
                if(!is_null($callback)){
                    $pos = array_search($sourceKey, ['email', 'emailmethod']);
                    $callback->setArgument($pos, $value);
                    $this->callbacks->set($callback);
                    return $callback;
                }
            default:
                return new StringData($key, $value);
        }
    }
}

class DataTranscriber implements Transcriber
{
    private $template;
    private $data;
    private $factory;

    private $transcribingMode = false;

    public function __construct(DataCollection $data, DataFactory $factory)
    {
        $this->data = $data;
        $this->factory = $factory;
    }

    public function canTranscribe($value): bool
    {
        if($value == '<BEGIN>'){
            $this->transcribingMode = true;
            return false;
        }

        if($value == '<END>'){
            $this->transcribingMode = false;
        }

        return $this->transcribingMode;
    }

    public function transcribe(array $body): DataCollection
    {
        $data = $this->collectData($this->data, $body);

        return $data;
    }

    public function collectData(DataCollection $collector, array $array, $parents = []): DataCollection
    {
        foreach($array as $key => $value){
            if($this->canTranscribe($value)){
                $value = $this->parse($key, $value, $parents);
                $parents[] = $key;
                if(is_array($value)){
                    $this->collectData($collector, $value, $parents);
                } else {
                    $data = $this->factory->fromSourceKey($parents[1], $key, $value);
                    if(!is_null($data->value())){
                        $collector->set($data);
                    }
                }
                array_pop($parents);
            }
        }
        return $collector;
    }

    public function parse($key, $value, $parents = [])
    {
        if(is_string($value)){
            if(key($parents) !== NULL){
                $keys = $this->data->getAliases($key);
                if(count($keys) > 1 || $keys[0] !== $key){
                    return \array_fill_keys($keys, $value);
                }
            }

            end($parents);
            if(key($parents) === NULL && false !== strpos($value, '=')){
                list($key, $value) = explode('=', $value, 2);
                return [$key => urldecode($value)];
            }

            if($key === 'directives'){
                return explode(',', $value);
            }

        }

        return $value;
    }
}

interface Template
{
    public function render(DataCollection $data): string;
}

class ArrayTemplate implements Template
{
    public $template;

    public function __construct(array $template = [])
    {
        $this->template = $template;
    }

    public function render(DataCollection $data): string
    {
        $output = array_reduce($this->template, function($output, $key) use($data){
            $output[] = $data->getValueByKey($key) ?? null;
            return $output;
        }, []);
        ksort($output);
        return implode("\n", array_filter($output));
    }
}

class Script
{
    private $client;
    private $transcriber;
    private $template;
    private $templateData;
    private $factory;

    public function __construct(HttpClient $client, Transcriber $transcriber, Template $template, DataCollection $templateData, DataFactory $factory)
    {
        $this->client = $client;
        $this->transcriber = $transcriber;
        $this->template = $template;
        $this->templateData = $templateData;
        $this->factory = $factory;
    }

    public static function run(string $host, int $port, string $script, string $settings = '')
    {
        $client = new ScriptClient($settings);

        $templateData = new DataCollection;
        $templateData->setAliases([
            'doctype'   => 0,
            'head1'     => 1,
            'robots'    => 8,
            'nocollect' => 9,
            'head2'     => 1,
            'top'       => 2,
            'legal'     => 3,
            'style'     => 5,
            'vanity'    => 6,
            'bottom'    => 7,
            'emailCallback' => ['email','emailmethod'],
        ]);

        $factory = new DataFactory;
        $factory->setStaticData([
            'doctype' => '<!DOCTYPE html>',
            'head1'   => '<html><head>',
            'head2'   => '<title>Partridges bitcoincashstandards.org</title></head>',
            'top'     => '<body><div align="center">',
            'bottom'  => '</div></body></html>',
        ]);
        $factory->setCompressedData([
            'robots'    => 'H4sIAAAAAAAAA7PJTS1JVMhLzE21VSrKT8ovKVZSSM7PK0nNK7FVSsvPyckv18nLTyxKzsgsS1Wys8GnPC8_My8ltULJDgBGxg-KVQAAAA',
            'nocollect' => 'H4sIAAAAAAAAA7PJTS1JVMhLzE21VcrL103NTczM0U3Oz8lJTS7JzM9TUkjOzytJzSuxVdJXsgMAKsBXli0AAAA',
            'legal'     => 'H4sIAAAAAAAAA7VabXPbNhL-fr8C59zk3JnEsdPESY6uZ1RHadRp7ZzlpNOPEAlSOFOECoBS1F9_z-6ClOQqbKfX60wjmS942X12n2cXuoh6VhuVm7oOS53bpvrm6PRIzZwvjOevdGupiyLduryI_vJvF7G4vChdE1WIm9p8c0Tfn5Z6YevNv1TuWm-Nf6IWrnE0rMmOLh8_Ojs_zdTAR_rjYpaGzl3t_DeP3vF_l_HiGV29vHg2ezDWxcxfPm5mYZnRt7u5UWszCzaaQ8PoNEzp3YIej05tXKtCO_uPyaOKdM3FufEqGr8IqnIrQ9d-Mo8fvXmdYVjMenrGkz8_y_Cqxo03Wc5P4eLZi8zoQmGIhcq1N2V78fenT2UVs9o2hdIh2BA1llaYha5cZXP19Oklva8r08TALxReL7QqXB69bQy-tHBUYSsbVa0XcJldyXuydZr5LCsPbblMW6ZHzunB0xdZ2oRt2AZY6wlPOsfS4Wh4VVsf6KPhv5cOa57Z2saNqjwcup2Y7K3z3IRAQzWyjuZp9LoJpfEEr0P2O6GLyWS_63v3Rd8P42hKL-zDY7o0udW18ibAsDn7-9o1T9-3C90cmnue5v5ksXZHj4elpT2cZsaHJ2omOxbj4m9LjywFCHILBgOQChNsRRfwHru6jW4Bm7zKos11TSO-zja9WcZ04U2m6eNlJncVwjAhLAQTGPiTqGzAbvI0V2N_FQMXPEntGnZs6byZOfYlPGdODu10mXb6s2uHIkfn97INNsact0AXXmJ9asU7OXuVGdW4yCsMUZbmAe7Hj168wGO8pHvn7inuFtpXtoFLAD4fZ97oe1pmY_zKtYGW2xTPnB9aMU2DmVujXEmRFwDJLhxfZpGn0ytta0523jRmbRCHiwYg2ChdAqeIO1VZCkpM6MTKtAzx69yGofkfP3qNHc8kgr0xCxqNDYNwF3dQujjN9kH_JbiXu3BPyD344P2fi4vdeBjrfK6WMDzsQEuXPSTYNSGq3axhlLgSJqbnjo--bwvL4fT40fnLLI_WpXyivGy7QqaDcSVe1Ijx0GLGWq8PWrToLQrjeSNGXPKiYMql8QDyAr4znLjIT6fPeX_4MKAsfr5WPShPM3YAUCBjbOQWrbRRnJG8E8BW4pfXXyM8CS9tE3RsvY6YbdE2hW446fkV363deukd0FbZlWkUSMIH8xmPBuSX6NsFRRz-xRs2NyfqCzFl0nZzJ4vtrKdnGBEO4bmCgX-wDlW0BNfQ-spg9ZSBJRmYRY-rIbSYP4aWYWzO_yANw5JviB27nQWExKHx8jReShJqTi8v9IZ3Ht0G0QovNBy4wcRYS3D19KOXSyNgOTR6SKMHtzBrRsBMvC2YgGOIviYFLU4WfZ5xngf5cTioZhuyuDeZTu5u8O15Nh0K3NF3twiIN9lY3dGrH0a3dz8P8drNrZoi4b96nb2dXH_HNPURA5xn0_G_P46v79S3AjpAq_WQJkDePSX9iGQZgc5oG0qVZBkryLgdjzDAK0k4nccg2v7fyu2O9337Y8-8W1Ss5xaBvxE_97KJN7YylYma1x7awnlbQtoEU5dlW5e2rjvaCl1SB1AoTEr5XiMa6RG66xv6BkEi-SckZurgxeT8IKmBLUTrMWfUtSi7gxTZOWzUbIY0ahIUNFwBmVa0uj7GSl5gAeu5O_RmtcNkW0EHCqrmMW2BVvs1WLShjLT_3JvMxrlrIxvFfKZLz3uukf8vP4yvJqMf9ijlh8nV-Ho63rt2u-s4OCRNDs-A3G0QXoAOIrc1DrRtqwbrSfrVhxPVqyn1ibVQUyAxQikNpSTvkoyKeI6sNmT83nnkUJKeopcNRKrlzBCC6AxEx64RsIcIIldfKg0670G0b80bO71MQi1pC4HWQvP21jVPDyHfGCg5NWsFgjbm84TbJMgqZHRRY1WtG2hoD7EN6UePFDIIKWztQcWQ-K6AL3OK-V2drxMMhKlrCEEsCrb-OFX_eHkqiEuyx9tZyzEFmuIbBURvSnzw06RJUrwRgzI3221knGX_DAoFiq1Jd3aR9zxLzB5CvVHgwDnqgviH-Ef_Dv9ss8jowwdgkzXFLhx5q1ERiwdJ4zY-2ZZdygrVYNomCW3YLhUkbIHm6KuhhYW5FqFdq9lBhHRwRVHIBhWNbhuIHBA0lIC6YgG0rCVOOt2DIlN0oZiOWd56AwOubScwG5Wgxnf1rh84LaeykOsqSxQYdL2gsLQLlHFIYPhsGwsZvMePa35r5m0BoIW583GtSZNxyCJrsIaHTSttk9rDEmhINjgNhcUbQqEY2TEhGsLOi_MHevbPFnEXdAGcOobLX4E93k53U5LMoFdJklZUr3tCr_G8eMhLSk_JelbM9EtrEEaOlD0FYe4tV1lqrbF3sIqau3VHKzLRMkUW573Smg5TQQnUGvtLLx3gk1oaBWSdNsXUoLyhxNNno_cM7Tv1fnT7acyaE_-8yMD9Txinw9kPr16zvFI_jqf89ui78fH0K371vYI-eQWj3r0fT6E_xsTFf4UI6CJ7dP12KIaubq7fTu4m7MjrA34MfTnEgdE5TW3z2oIzW6cb5UHbxzES112XktNASdxTUTJgt5-kJ3SiWFgJx0tSKChhFFYaINBRZubdPUQ9riJoK9YlgI5pyDAoYnsByiGKm0Edb1Mo8Z5ANfS1O26RogRWts91G4ze1cJXQ_WQbkHwvm9YCRIFeShxqFs132lAdRlarX3KLxH7WUrA7GgCwd4dB9_kCp-vvs5u9r12-e7m9rde9KCGlONy0ySfcpoTFmEBbRuWeHVbmIPk736jeXKvRc5IGpZhqckiXQZinrPXWbfRU0IH8XxOjRSiJivvHcRBV6s7r6o9WZCm7vDV93aMVI4gPMcFKNFgN-u2B5MK1cEy_gsRXezpGWI2Q8U4KFcsyynmoAztypovVJX324FRFnf9x8USSKYyqi8cuc2pqxQ81DRhiZDcKf0lWQoHACZhNZJaStDlqkBdFUiflI5EeLtUpebACcavdFeM7HRG2WaHmyldBvEmd5WwTHGyA9Zvf9hXqkDreTbi1svedUpQu39___F28ttqROfSFqraug6q9FZTgBKJpiiHtrErIXViyhStCvCB6JFu7xr8SRe0tzuxfppJJWI8CePZBqGZ4qPW684Up1lH8NSNSFlhAEPHYGqRmZ_tol2o2s5MTTWfLlbGR9Ss4LISHM6ZyaijkUB1wW_BpJZWzU5cbjpNKnmziUE6IbLKuDZGVjiEvFGxYIVgVDdRJ76mKOSo4cE82nBFwpSFFxq6yVMOpQOgp5Gl7Ki3TifxqFQb9okOCcHm1Pgb6lYsH2a-m3d7GPk04UyI4n4v2V1-TOSMmnYfY7uAgu5XH5I6SsmeE58USWupXxtS6oUuS6gSX9iGC4Dkh9eZpBFR2UYdf4HKut0cccMCIiWhiZhsoVFvAG-CapfndRso_ODzvGWkgLi4t9mX4L2EPc0o6ofcXRLKxcEb1dsSEXiWjSA0hEBIvUxZyNwg4nD_jlWA4kvvx2ryVnoconXeTcbbaBTNMlSUbR1xyzs0ZQlBl1vaWUG9_FZ6eviOeFBz81lXnLWw5U-TK2mkUe-_lGpqfPJXqCJCkix9DzX7NXVCSVTHR0nvGPWTIHzWFdQpuJsWK17WOSIW8V3z7hAPpS2S5jCCkGi81B5IAMPkYxvBFr23otYWJYpjXO3c2SV5WtXRHfdBBB-lmnYKW7F4Emyb1E-d67qWokwe98p1u_Nrm6rkN9nWxzL24Ti1abWYcWXzbklvslTKho6FpL-f0ql7ENPXN5DFLygJv3pOIHuZ_Ti63g9zVjjUw9uNXzq8SjJyIOiimEEelIoX5XXH_3zaUafGcQ4EIhWrOZ2e5Xqp4trNNiWVLVA4v24gJaM8kioQK8k0xK3ioYA73GTqYtLFRNyeINLV8hFctmcYgJZbClQjEn9zfep5paAEsAkdhmEdARijKytLctJ8zs0yCd9o8nmDJVRds4KfOdiiWe7ki3n7EAFJXjAndAGwow0O4rjozQ991IdPLoe3oZ3xQarAA4A8aLCuBivYyBCT2qd2atUupHOa147a-NjbKomfNGYjZckTic8NHWZs1DJs8rnba8tU3XkT3vnVSDdno30BNMz1MlGuGIHqwIGAXSXOq3V8gG9OgW-h0yc3nEd_m2MMH_ChfGmruUQr9AfdKLd0eZYt2AEpHLfekaMz6aycZzoBaoipEcuU2ITyj75KxzCOipGtzlG8bHpC8naLgmc5h-0gtgROMj2QYbs-fFffsJBM7SmqLmTcKOcpikk2CYRUgjdy5DhXdIwIPgySMHjMwSiPvfhMMdwGSsWBNDM-K9SFvNZK-6JrNe-5hnr3-xLh24M0wAFAEtcjhE7kRCyJGVjQlmoNF7RgKn5426TzrDKExnStC6XbexKsSCIGfNDOxJbepUZi43x_WE_9jtK52oY5dobYjG1D50l4Xk60jQ7UMttp6BCX392OrqfMbuPbW86ccjZxO9QaQ_0vGmDC_368G08VsnBnqv-Zb2Wcw328LvvYQIksdVrdzNkCFq9tRfrgl9bWkTrDOWpUDnjOZX1FWdi-rB9KvknnIUeL_tqGEUi8SR1pHfVMBzk2lO5_qtLBceaIr5ywcqSalYkk7HSeZ6ZrZIJvUn_YeXHzVv72J_33XdXWBlbDrBNSK3vdIKV3rJ7WsIPeWw7RA-lEIXXVm9S0kKNdkpjuCf3CJjXfpBYEuex2HanabFODbrFN2-BPybx6MQtqvik8lzA5fN2wMAlgo1qtnICTG_U7S41t6s-Se6U2B43KbyZQdq7sw45LakbAkClNEOfy1aWtuTzt2sepjYuMMZQddposKNhFg1FL8glwwL9RoOqkrxRLPm7Yt_VuCcoH4bINURFRcvXQ-ZAcUJNI2kFKV1tT_sy3zt8TbVqMMcQ8ps81cDI86jrbALj_kYycCt1TEmZIvtwLmHFP6ne6b6lz00gzopTaBMt1yFR9kW26Dj6vv-sqJaIScmFX94k_dd_apnjY2kqae0gOyNFGKrz6Qw0WhDG5xUWzFRd8R3D0uP85jrRLdhLnjt5NjWib36M-UXCOxO6NGqm795xPb99uHx9dXY0_cM4cXV-Nh9o6I8jc89fbGfdy6jP-ed4z_lkfvuD-fwHjH5cY4ycAAA',
            'style'     => 'H4sIAAAAAAAAAyXMSwrDMAwFwKsEss2vWztk6XvIjkwNQq_IKqSU3L2LzgFm7_4RPmjxJ_K7CJyyfQsEFsaUUqxQDxlyDo_tdQ1kjWTqpH3ubK1G58vnkwuMvEGDQjne-_p_fztlbNNfAAAA',
            'vanity'    => 'H4sIAAAAAAAAA22SUU_CMBDHv8qlvApDVBLKWIwEY0wUgvrgY7eWrVp7zfVg8u3tJr6oaS65S3u__7_X5qxKZ6AyzsWgKuvrhRiLrgxK61NZImlDXRb56MxClKp6rwn3XsvBbDabt1ZzIycX4_A5F0XOlELDQTlb-4VgDD-NJ6iE8_AJkxRXKS5T17fEkGzdsIzorO6PDJbLZUdM3jycGDv0LEt0Gjo9UGSVO4vKx2E0ZHfzCh2SHEyn03lSlp2ngNGyRS_JOMX2YBLzOs86apFnrP_YhVPuzI4F_DJ_kVTHaV1-31ZBQ2a3EA1zkFnWtu0oEL6Zihv05hiQR0h1JqByKsbEb7DcVw7T5EkUD6uHm9UW1rew2a7vV8tnuFs_rl5hs37OM1XkJf2rsPfJ_Meowg_xL_Yp7cKdooOJbAg2hJwMpRHAo-EW6b0DJ5sHq42G8ggvPbCX7AeSdY-Y9b-j-AJaINOpJQIAAA',
        ]);
        $factory->setCallbackData([
            'emailCallback' => function($email, $style = null){
                $value = $email;
                $display = 'style="display:' . ['none',' none'][random_int(0,1)] . '"';
                $style = $style ?? random_int(0,5);
                $props[] = "href=\"mailto:$email\"";
        
                $wrap = function($value, $style) use($display){
                    switch($style){
                        case 2: return "<!-- $value -->";
                        case 4: return "<span $display>$value</span>";
                        case 5:
                            $id = '7ebr588';
                            return "<div id=\"$id\">$value</div>\n<script>document.getElementById('$id').innerHTML = '';</script>";
                        default: return $value;
                    }
                };
        
                switch($style){
                    case 0: $value = ''; break;
                    case 3: $value = $wrap($email, 2); break;
                    case 1: $props[] = $display; break;
                }
        
                $props = implode(' ', $props);
                $link = "<a $props>$value</a>";
        
                return $wrap($link, $style);
            }
        ]);

        $transcriber = new DataTranscriber($templateData, $factory);

        $template = new ArrayTemplate([
            'doctype',
            'injDocType',
            'head1',
            'injHead1HTMLMsg',
            'robots',
            'injRobotHTMLMsg',
            'nocollect',
            'injNoCollectHTMLMsg',
            'head2',
            'injHead2HTMLMsg',
            'top',
            'injTopHTMLMsg',
            'actMsg',
            'errMsg',
            'customMsg',
            'legal',
            'injLegalHTMLMsg',
            'altLegalMsg',
            'emailCallback',
            'injEmailHTMLMsg',
            'style',
            'injStyleHTMLMsg',
            'vanity',
            'injVanityHTMLMsg',
            'altVanityMsg',
            'bottom',
            'injBottomHTMLMsg',
        ]);

        $hp = new Script($client, $transcriber, $template, $templateData, $factory);
        $hp->handle($host, $port, $script);
    }

    public function handle($host, $port, $script)
    {
        $data = [
            'tag1' => 'fc4957eb1e2c049ce7f075d8e3a28b1b',
            'tag2' => '2c4fe6005c473c57cd046f20939b709e',
            'tag3' => '3649d4e9bcfd3422fb4f9d22ae0a2a91',
            'tag4' => md5_file(__FILE__),
            'version' => "php-".phpversion(),
            'ip'      => $_SERVER['REMOTE_ADDR'],
            'svrn'    => $_SERVER['SERVER_NAME'],
            'svp'     => $_SERVER['SERVER_PORT'],
            'sn'      => $_SERVER['SCRIPT_NAME']     ?? '',
            'svip'    => $_SERVER['SERVER_ADDR']     ?? '',
            'rquri'   => $_SERVER['REQUEST_URI']     ?? '',
            'phpself' => $_SERVER['PHP_SELF']        ?? '',
            'ref'     => $_SERVER['HTTP_REFERER']    ?? '',
            'uagnt'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        $headers = [
            "User-Agent: PHPot {$data['tag2']}",
            "Content-Type: application/x-www-form-urlencoded",
            "Cache-Control: no-store, no-cache",
            "Accept: */*",
            "Pragma: no-cache",
        ];

        $subResponse = $this->client->request("POST", "http://$host:$port/$script", $headers, $data);
        $data = $this->transcriber->transcribe($subResponse->getLines());
        $response = new TextResponse($this->template->render($data));

        $this->serve($response);
    }

    public function serve(Response $response)
    {
        header("Cache-Control: no-store, no-cache");
        header("Pragma: no-cache");

        print $response->getBody();
    }
}

Script::run(__REQUEST_HOST, __REQUEST_PORT, __REQUEST_SCRIPT, __DIR__ . '/phpot_settings.php');

