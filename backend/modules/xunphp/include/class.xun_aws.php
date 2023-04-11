<?php
include_once 'include/aws/aws-autoloader.php';

use Aws\S3\S3Client;
use Aws\Ses\SesClient;
use Aws\Exception\AwsException;

class XunAws{
    function __construct($db, $setting){
        $this->db = $db;
        $this->setting = $setting;
    }

    function s3Client(){
        $region = "ap-southeast-1";

        $s3config = [
            's3-access' => [
                'region' => $region,
                'version' => 'latest'
            ]
        ];

        $s3 = new S3Client([
            'profile' => 'default',
            'version' => $s3config['s3-access']['version'],
            'region' => $s3config['s3-access']['region']
        ]);

        return $s3;
    }

    private function SesClient(){
        $SesClient = new SesClient([
            'profile' => 'ses',
            'version' => '2010-12-01',
            'region'  => 'us-east-1'
        ]);

        return $SesClient;
    }

    public function generate_put_presign_url($params)
    {
        $s3Client = $this->s3Client();

        $s3_bucket = trim($params["s3_bucket"]);
        $s3_file_key = trim($params["s3_file_key"]);
        $content_type = trim($params["content_type"]);
        $content_size = trim($params["content_size"]);
        $expiration = trim($params["expiration"]);

        try {
            // //Creating a presigned URL
            $cmd = $s3Client->getCommand('PutObject', [
                'Bucket' => $s3_bucket,
                'Key' => $s3_file_key,
                'ACL'    => 'public-read',
                'ContentType' => $content_type,
                'ContentLength' => $content_size,
            ]);

            $request = $s3Client->createPresignedRequest($cmd, $expiration);

            // Get the actual presigned-url
            $presigned_url = (string) $request->getUri();

            $get_url = $s3Client->getObjectUrl($s3_bucket, $s3_file_key);

            return array("put_url" => $presigned_url, "get_url" => $get_url);

        } catch (Exception $ex) {
            $error = $ex->getMessage();
            return array("error" => $error);
        }
    }

    public function send_ses_email($params){
        global $setting;
        global $xunAWSWebservices;

        $SesClient = $this->SesClient();
        $sender_email = $setting->systemSetting["systemEmailAddress"];

        // array
        $recipient_emails = $params["recipient_emails"];

        $subject = $params["subject"];
        $html_body = $params["html_body"];
        $char_set = "UTF-8";

        // Specify a configuration set. If you do not want to use a configuration
        // set, comment the following variable, and the
        // 'ConfigurationSetName' => $configuration_set argument below.
        // $configuration_set = 'ConfigSet';
        $aws_params = [
            'Destination' => [
                'ToAddresses' => $recipient_emails,
            ],
            'ReplyToAddresses' => [$sender_email],
            'Source' => $sender_email,
            'Message' => [
              'Body' => [
                  'Html' => [
                      'Charset' => $char_set,
                      'Data' => $html_body,
                  ],
                //   'Text' => [
                //     'Charset' => $char_set,
                //     'Data' => $plaintext_body,
                // ],
              ],
              'Subject' => [
                  'Charset' => $char_set,
                  'Data' => $subject,
              ],
            ],
            // If you aren't using a configuration set, comment or delete the
            // following line
            // 'ConfigurationSetName' => $configuration_set,
        ];

        $filtered_aws_params = $aws_params;
        unset($filtered_aws_params["Message"]);
        $jsonParams = json_encode($filtered_aws_params);

        $starttime = time();
        $createTime = date("Y-m-d H:i:s");
        
        $webservics_id = $xunAWSWebservices->insertWebserviceData($jsonParams, $createTime, "sendEmail");

        try {
            $result = $SesClient->sendEmail($aws_params);
            $messageId = $result['MessageId'];
            $metadata = $result["@metadata"];
            $statusCode = $metadata["statusCode"];
            // echo("Email sent! Message ID: $messageId"."\n statusCode: " . $statusCode);

            $status = "ok";
            $data_out = "MessageId: $messageId\nstatusCode: $statusCode";
            $complete_time = date("Y-m-d H:i:s");
            $processed_time = time() - $starttime;
            $xunAWSWebservices->updateWebserviceData($webservics_id, $data_out, $status, $complete_time, $processed_time);
            
            return array("code" => 1, "status" => "ok", "message_id" => $messageId);
        } catch (AwsException $e) {
            // output error message if fails
            $error_message = $e->getMessage();
            $aws_error_message = $e->getAwsErrorMessage();
            $complete_time = date("Y-m-d H:i:s");
            $processed_time = time() - $starttime;
            $data_out = "Error: $error_message\nAwsErrorMessage: $aws_error_message";

            $xunAWSWebservices->updateWebserviceData($webservics_id, $data_out, $status, $complete_time, $processed_time);

            return array(
                "code" => 0,
                "error_message" => $error_message,
                "aws_error_message" => $aws_error_message
            );
        }
    }

    public function s3_put_object($params)
    {
        $s3Client = $this->s3Client();

        $s3_bucket = trim($params["s3_bucket"]);
        $s3_file_key = trim($params["s3_file_key"]);
        $file_body = trim($params["file_body"]);
        $content_type = trim($params["content_type"]);

        try {
            $result = $s3Client->putObject([
                'Bucket' => $s3_bucket,
                'Key'    => $s3_file_key,
                'Body'   => $file_body,
                'ContentType'     => $content_type,
                'ACL'             => 'public-read'
                // 'SourceFile' => 'c:\samplefile.png' -- use this if you want to upload a file from a local location
            ]);

            $object_url = $result["ObjectURL"];
            return array("object_url" => $object_url);

        } catch (Exception $ex) {
            $error = $ex->getMessage();
            return array("error" => $error);
        }
    }
}
