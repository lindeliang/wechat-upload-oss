<?php

namespace app\controller;

use think\Request;
use think\facade\Config;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

use app\BaseController;

class Api extends BaseController
{
    private $ak='';
    private $sk='';
    private $host='';
    private $bucket='';
    private $endpoint='';
    private $roleArn='';
    private $roleSessionName='';
    
    public function __construct(Request $request)
    {
        //配置阿里云参数
        empty($this->ak) && $this->ak = Config::get('upload.oss_ak');
        empty($this->sk) &&  $this->sk = Config::get('upload.oss_sk');
        empty($this->host) &&  $this->host = Config::get('upload.oss_host');
        empty($this->bucket) &&  $this->bucket = Config::get('upload.oss_bucket');
        empty($this->endpoint) &&  $this->endpoint = Config::get('upload.oss_endpoint');
        empty($this->roleArn) &&  $this->roleArn = Config::get('upload.oss_role_arn');
        empty($this->roleSessionName) &&  $this->roleSessionName = Config::get('upload.oss_role_session_name');
    }

    /**
     * 阿里云Sts凭证
     */
    public function getStsToken()
    {
        AlibabaCloud::accessKeyClient($this->ak, $this->sk)
            ->regionId('cn-hangzhou')
            ->asDefaultClient();

        try {
            $result = AlibabaCloud::rpc()
                ->product('Sts')
                ->scheme('https') // https | http
                ->version('2015-04-01')
                ->action('AssumeRole')
                ->method('POST')
                ->host('sts.aliyuncs.com')
                ->options([
                    'query' => [
                        'RegionId' => "cn-hangzhou",
                        'RoleArn' => $this->roleArn,
                        'RoleSessionName' => $this->roleSessionName,
                    ],
                ])
                ->request();
                
            $resultObj = $result->toArray();
            $credentials = $resultObj['Credentials'];
            $credentials['host'] = $this->host;
            return json($credentials);
        } catch (ClientException $e) {
            return error($e->getErrorMessage());
        } catch (ServerException $e) {
            return error($e->getErrorMessage());
        }
    }
}