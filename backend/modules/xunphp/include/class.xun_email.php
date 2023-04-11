<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the email templates.
     * Date  29/06/2017.
    **/
    class XunEmail {
        
        function __construct($db, $post) {
            $this->db = $db;
            $this->post = $post;
        }
        
        function getXunEmailHeaderFooter($companyName = null, $logoPath = null){
            global $setting;

            if(!$companyName){
                $companyName = $setting->systemSetting["companyName"];
            }

            if(!$logoPath){
                $logoPath = $setting->systemSetting["emailLogoImagePath"];
            }
            
            $header ="<!doctype html>
                <html xmlns=\"http://www.w3.org/1999/xhtml\">
                <head>
                    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
                    <style>
                        *{
                            font-family:Helvetica Neue, Helvetica, Arial;
                            font-size:13px;
                            -webkit-font-smoothing:antialiased;
                        }
                        a{
                            text-decoration:none;
                            color:white;
                        }
                        a:hover{
                            text-decoration:none;
                            color:white;
                            cursor:pointer;
                        }
                    </style>
                </head>
                <body style=\"width: 630px; margin: 0 auto;\">
    
                <div style=\"box-shadow: 0px 0px 8px 3px rgba(0,0,0,0.15);\">
                            <div style=\"padding:45px 15px;text-align:center;background-color: #273333;border-bottom:1px solid #e8eaf1;box-shadow: 0 1px 50px 0 #e8eaf1;position:relative;border-top-left-radius: 5px;border-top-right-radius: 5px;color:#F5F6F7;\">
                                <img src=\"" . $logoPath . "\" style=\"width:100%; max-width:180px;display: block;text-align: center;margin: auto;margin-bottom: 2.5rem;\">";
                                
             $footer = "<p style=\"font-size: 12px;line-height: 1.6;margin-top: 40px;\">Best Regards,<br>Customer Support,<br>" . $companyName . "</p></div></div></body></html>";
    
             return array('header' => $header, 'footer' => $footer);
        }

        function getActivationEmailHtml($business_name, $verification_code, $business_email, $include_email = true, $companyName = null, $server = null, $button_gradient = null, $logoPath = null){
            global $setting;
            global $config;
            if(!$companyName){
                $companyName = $setting->systemSetting["companyName"];
            }
            if(!$server){
                $server = $config['server'];
            }

            if(!$button_gradient){
                $button_gradient = $setting->systemSetting["emailButtonGradient"];
            }

            $emailBody = "<p style=\"font-size: 22px; letter-spacing: .5px; margin-bottom: 10px;\">Welcome, "
            . $business_name . "!</p>
            <p style=\"font-size: 12px; margin-bottom: 0;\">Glad to have you on board.</p></div>
            <div style=\"background:#fff;padding: 20px;border:1px solid #e8eaf1;border-top: none;border-bottom-left-radius:  5px;border-bottom-right-radius: 5px;\">
            <p style=\"font-size:12px;\">Please activate your email address by clicking the button below:</p>
            <div style=\"text-align: center;margin: 60px auto;\">
            <a href=\"https://" . $server . "/verification.php?verify_code=" . $verification_code;

            if($include_email){
                $emailBody .= "&business_email=" . $business_email;
            }
            
            $emailBody .= "\" style=\"background: linear-gradient(". $button_gradient .");padding: 1rem 2rem;border-radius: 60px;color:#fff!important;\"><strong>Activate Account</strong></a>
            </div>                                              
            <p style=\"font-size:12px;\">Once activated, you'll be able to continue sign up your " . $companyName . " account.</p>";

            $html_header_footer = $this->getXunEmailHeaderFooter($companyName, $logoPath);
            $header = $html_header_footer["header"];
            $footer = $html_header_footer["footer"];
            
            $html = $header . $emailBody . $footer;

            return $html;
        }

        function getForgotPasswordHtml($password, $companyName = null, $logoPath = null){
            global $setting;

            if(!$companyName){
                $companyName = $setting->systemSetting["companyName"];
            }
              
            $emailBody = 
                "<p style=\"font-size: 22px; letter-spacing: .5px; margin-bottom: 10px;\">Forgot your password?</p>
                <p style=\"font-size: 12px; margin-bottom: 0;\">Let's get you a new one.</p>
                </div>
                <div style=\"background:#fff;padding: 20px;border:1px solid #e8eaf1;border-top: none;border-bottom-left-radius:  5px;border-bottom-right-radius: 5px;\">
                <p style=\"font-size:12px;\">A password reset was requested for your " . $companyName . " account. The password has been reset and you can now sign in with the following credentials:</p>
                <div style=\"margin: 60px auto;\">
                <p style=\"font-size: 20px; font-weight: bold;\">Password: " . $password . "</p>
                </div>
                <p style=\"font-size:12px;\">If you did not request this password reset, please contact us.</p>";

            $html_header_footer = $this->getXunEmailHeaderFooter($companyName, $logoPath);
            $header = $html_header_footer["header"];
            $footer = $html_header_footer["footer"];
            
            $html = $header . $emailBody . $footer;

            return $html;  
        }

        function getEmailHtml($emailBody, $header = true, $footer = true){
            $html_header_footer = $this->getXunEmailHeaderFooter();
            
            if($header == true){
                $header = $html_header_footer["header"];
            }else{
                $header = "";
            }

            if($footer == true){
                $footer = $html_header_footer["footer"];
            }else{
                $footer = "";
            }
            
            $html = $header . $emailBody . $footer;

            return $html;
        }

        function getStoryActivationEmailHtml($name, $verification_code, $email, $companyName){
            $system_info = $this->getStoryInfo();

            $server = $system_info["server"];
            $button_gradient = $system_info["buttonGradient"];
            $logoPath = $system_info["logoPath"];

            $include_email = false;
            return $this->getActivationEmailHtml($name, $verification_code, $email, $include_email, $companyName, $server, $button_gradient, $logoPath);
        }

        function getNuxPayActivationEmailHtml($name, $verification_code, $email, $companyName){
            $system_info = $this->getNuxPayInfo();
            // print_r($system_info);

            $server = $system_info["server"];
            $button_gradient = $system_info["buttonGradient"];
            $logoPath = $system_info["logoPath"];

            $include_email = false;
            return $this->getActivationEmailHtml($name, $verification_code, $email, $include_email, $companyName, $server, $button_gradient, $logoPath);
        }

        function getStoryForgetPasswordEmailHtml($email, $password, $companyName){
            $system_info = $this->getStoryInfo();

            $logoPath = $system_info["logoPath"];

            $include_email = false;
            return $this->getForgotPasswordHtml($password, $companyName, $logoPath);

        }

        function getPayForgetPasswordEmailHtml($password, $companyName){
            $system_info = $this->getNuxPayInfo();

            $logoPath = $system_info["logoPath"];

            $include_email = false;
            return $this->getForgotPasswordHtml($password, $companyName, $logoPath);

        }

        function getStoryInfo(){
            global $setting;

            return array(
                "server" => $setting->systemSetting["storyServer"],
                "logoPath" => $setting->systemSetting["storyEmailLogoImagePath"],
                "buttonGradient" => "90deg, rgba(246,105,30,1) 0%, rgba(238,65,54,1) 98%, rgba(238,64,54,1) 100%"
            );
        }

        function getNuxPayInfo(){
            global $setting;

            return array(
                "server" => $setting->systemSetting["payServer"],
                "logoPath" => $setting->systemSetting["payEmailLogoImagePath"],
                "buttonGradient" => "to right, #51c2c6 0%, #51c2db 100%"
            );
        }

        function getEmailProvider($company_name) {

            $db = $this->db;
            $db->where("company", $company_name);
            $db->where("name", "noreply_mail");
            $db->where("disabled", 0);
            $db->where("deleted", 0);
            $db->orderBy("priority", DESC);
            $emailProviderDetail = $db->getOne("provider");

            $db->where("source", $company_name);
            $db->where("deleted", 0);
            $siteDetail = $db->getOne("site", "domain, company_name, company_address, support_email, reseller_website, image_url_path, theme_color");
            
            return array("company"=>$emailProviderDetail['company'], "username"=>$emailProviderDetail['username'], "password"=>$emailProviderDetail['password'], "siteDomain"=>$siteDetail['domain'], "companyName"=>$siteDetail['company_name'], "companyAddress"=>$siteDetail['company_address'], "supportEmail"=>$siteDetail['support_email'], "resellerWebsite"=> $siteDetail['reseller_website'], 'imageUrlPath' => $siteDetail['image_url_path'], 'themeColor'=> $siteDetail['theme_color']);
        }

        // function getStyleCss() {

        //     $style = '<style>
        //     .loginBlock {
        //         display: block;
        //         width: 500px;
        //         padding: 3rem 3rem;
        //         max-width: 100%;
        //         background-color: #f4f7fa;
        //         background-size: cover;
        //         background-repeat: no-repeat;
        //         background-position: right 15%;
        //         color: #141414;
        //         font-family: Arial, Helvetica, sans-serif;
        //     }

        //     img.fusionLogo {
        //         display: block;
        //         margin: 0 auto;
        //     }

        //     .fusionMsgBox {
        //         background-color: #fff;
        //         border-radius: 8px;
        //         margin-top: 2rem;
        //         text-align: center;
        //         padding: 1rem 10%;
        //         box-shadow: 0 0 20px -10px #ccc;
        //     }

        //     .fusionEmailIcon {
        //         display: block;
        //         margin: 1.5rem auto;
        //     }

        //     .longLine {
        //         display: block;
        //         width: 100%;
        //         height: 2px;
        //         background-color: #e7e7e7;
        //         clear: both;
        //         margin: 2rem auto;
        //     }

        //     .fusionTxt1 {
        //         font-size: 18px;
        //         color: #48545c;
        //     }

        //     .fusionTxt2 {
        //         font-size: 16px;
        //     }


        //     .fusionTxt3 {
        //         font-size: 14px;
        //         // padding: 0 1rem;
        //         line-height: 1.3;
        //         margin: 20px 0 15px 0;
        //     }

        //     .fusionTxt4 {
        //         font-size: 8px;
        //         // padding: 0 1rem;
        //         line-height: 1.3;
        //         margin: 20px 0 15px 0;
        //         text-align: center;
        //     }

        //     a.mailto {
        //         color: #52c3cf;
        //     }
        //     a.fusionLinkBtn {
        //         display: block;
        //         width: 100%;
        //         background-color: #52c3cf;
        //         color: #fff;
        //         text-decoration: none;
        //         padding: 14px;
        //         border-radius: 4px;
        //         // text-transform: uppercase;
        //     }

        //     a.fusionLinkBtn:hover {
        //         text-decoration: underline;
        //     }

        //     .shortLine {
        //         display: block;
        //         width: 40px;
        //         height: 2px;
        //         background-color: #e7e7e7;
        //         clear: both;
        //         margin: 1.5rem auto;
        //     }

        //     .fusionSmallTxt {
        //         font-size: 12px;
        //         font-style: italic;
        //         color: #929191;
        //     }

        //     .bottomDiv {
        //         width: 500px;
        //         margin-top: 20px;
        //     }

        //     .bottomDiv2 {
        //         width: 500px;
        //         margin-top: 50px;
        //     }

        //     .bottomDiv3 {
        //         width: 500px;
        //     }

        //     .bottomImage{ 
        //         width:50%; 
        //         float:left; 
        //     } 


        // </style>';

        //     return $style;

        // }

        //tuned gmail
        function getRegistrationEmail($companyName, $verification_code){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";

            // $email_template = '<!DOCTYPE html>
            //     <html lang="en">
            //     <head>
            //         <meta charset="UTF-8">
            //         <meta name="viewport" content="width=device-width, initial-scale=1.0">
            //         <title>Email Verification</title>
            //         '.$this->getStyleCss().'
            //     </head>
            //     <body>
            //         <div class="loginBlock">
            //             <img class="fusionLogo" src="'.$emailProviderDetail['siteDomain'].'/images/email_logo.png" alt="" width="150px">
            //             <div class="fusionMsgBox">
            //                 <img class="fusionEmailIcon" src="'.$emailProviderDetail['siteDomain'].'/images/emailIcon.png" width="70px" alt="">
            //                 <h3 class="fusionTxt1">Verify your email address</h3> 
            //                 <div class="longLine"></div>
                            
            //                 <p class="fusionTxt3" style="text-align: left;">Thanks for signing-up with us.</p>

            //                 <p class="fusionTxt3" style="text-align: left;">In order to verify your email address, please enter OTP code below within 15 minutes.</p>

            //                 <p class="fusionTxt2">'.$verification_code.'</p>
                            
            //                 <div class="shortLine"></div>

            //                 <p class="fusionTxt3" style="text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'">'.$emailProviderDetail['supportEmail'].'</a></p>

            //                 <p class="fusionTxt3" style="text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

            //             </div>
                         
            //             <div class="bottomDiv">

            //                 <div class="bottomImage" style="text-align: right;">
            //                     <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
            //                 </div>
            //                 <div class="bottomImage">
            //                     <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
            //                 </div>
                            
            //             </div>

            //             <div class="bottomDiv2">

            //                 <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                            
            //             </div>

            //             <div class="bottomDiv3" style="text-align: center; margin-bottom: 10px; ">
            //                 <img src="'.$emailProviderDetail['siteDomain'].'/images/email_logo_footer.png" alt="" width="40px">
            //             </div>
                         

            //         </div>
            //     </body>
            //     </html>';


            $email_template = '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Verify your email address</title>
                    
                </head>
                <body>
                    <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                        <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                        <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                            <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                            <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Verify your email address</h3> 
                            <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>
                            
                            

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">In order to verify your email address, please enter OTP code below within 15 minutes.</p>

                            <p class="fusionTxt2" style="font-size: 18px;">'.$verification_code.'</p>
                            
                            <div class="shortLine" style="display: block; width: 40px; height: 2px; background-color: #e7e7e7; clear: both; margin: 1.5rem auto;"></div>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color:'.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                        </div>
                         
                        <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                            <div class="bottomImage" style="width: 50%; float: left; text-align: right;">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                            </div>
                            <div class="bottomImage" style="width: 50%; float: left;">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                            </div>
                            
                        </div>

                        <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                        </div>

                        <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                            <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                        </div>
                         

                    </div>
                </body>
                </html>';

            return array("html"=>$email_template, 
                            "emailSubject"=>"Verify your email address",
                            "emailFromName"=>$emailProviderDetail['company'], 
                            "emailAddress"=>$emailProviderDetail['username'], 
                            "emailPassword"=>$emailProviderDetail['password']);
        }

        //tuned gmail
        function getAutoRegistrationEmail($companyName, $verification_code){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";

            // $email_template = '<!DOCTYPE html>
            //     <html lang="en">
            //     <head>
            //         <meta charset="UTF-8">
            //         <meta name="viewport" content="width=device-width, initial-scale=1.0">
            //         <title>Email Verification</title>
            //         '.$this->getStyleCss().'
            //     </head>
            //     <body>
            //         <div class="loginBlock">
            //             <img class="fusionLogo" src="'.$emailProviderDetail['siteDomain'].'/images/email_logo.png" alt="" width="150px">
            //             <div class="fusionMsgBox">
            //                 <img class="fusionEmailIcon" src="'.$emailProviderDetail['siteDomain'].'/images/emailIcon.png" width="70px" alt="">
            //                 <h3 class="fusionTxt1">Welcome On Board</h3> 
            //                 <div class="longLine"></div>
                            
            //                 <p class="fusionTxt3" style="text-align: left;">Your '.$emailProviderDetail['companyName'].' account has been created successfully!</p>

            //                 <p class="fusionTxt3" style="text-align: left;">Login at <a class="mailto" href="'.$emailProviderDetail['siteDomain'].'">'.$emailProviderDetail['siteDomain'].'</a> with your email and password at below:</p>

            //                 <p class="fusionTxt2">'.$verification_code.'</p>
                            
            //                 <div class="shortLine"></div>

            //                 <p class="fusionTxt3" style="text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'">'.$emailProviderDetail['supportEmail'].'</a></p>

            //                 <p class="fusionTxt3" style="text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

            //             </div>
                         
            //             <div class="bottomDiv">

            //                 <div class="bottomImage" style="text-align: right;">
            //                     <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
            //                 </div>
            //                 <div class="bottomImage">
            //                     <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
            //                 </div>
                            
            //             </div>

            //             <div class="bottomDiv2">

            //                 <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                            
            //             </div>

            //             <div class="bottomDiv3" style="text-align: center; margin-bottom: 10px; ">
            //                 <img src="'.$emailProviderDetail['siteDomain'].'/images/email_logo_footer.png" alt="" width="40px">
            //             </div>
                         

            //         </div>
            //     </body>
            //     </html>';

            $email_template = '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Welcome Aboard</title>
                    
                </head>
                <body>
                    <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                        <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                        <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                            <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                            <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Welcome Aboard</h3> 
                            <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Your '.$emailProviderDetail['companyName'].' account has been created successfully!</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Login at <a class="mailto" href="'.$emailProviderDetail['siteDomain'].'/login.php?type=loginPage" style="color: #'.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['siteDomain'].'/login.php?type=loginPage</a> with your email and password at below:</p>

                            <p class="fusionTxt2" style="font-size: 18px;">'.$verification_code.'</p>
                            
                            <div class="shortLine" style="display: block; width: 40px; height: 2px; background-color: #e7e7e7; clear: both; margin: 1.5rem auto;"></div>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                        </div>
                         
                        <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                            <div class="bottomImage" style="width: 50%; float: left; text-align: right;">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                            </div>
                            <div class="bottomImage" style="width: 50%; float: left;">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                            </div>
                            
                        </div>

                        <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                        </div>

                        <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                            <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                        </div>
                         

                    </div>
                </body>
                </html>';


            return array("html"=>$email_template, 
                            "emailSubject"=>"Welcome Aboard",
                            "emailFromName"=>$emailProviderDetail['company'], 
                            "emailAddress"=>$emailProviderDetail['username'], 
                            "emailPassword"=>$emailProviderDetail['password']);
        }

        //gmail tuned
        function getForgotPasswordEmail($companyName, $verification_code){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";

            // $email_template  = '<!DOCTYPE html>
            //     <html lang="en">
            //     <head>
            //         <meta charset="UTF-8">
            //         <meta name="viewport" content="width=device-width, initial-scale=1.0">
            //         <title>Email Verification</title>
            //         '.$this->getStyleCss().'
            //     </head>
            //     <body>
            //         <div class="loginBlock">
            //             <img class="fusionLogo" src="'.$emailProviderDetail['siteDomain'].'/images/email_logo.png" alt="" width="150px">
            //             <div class="fusionMsgBox">
            //                 <img class="fusionEmailIcon" src="'.$emailProviderDetail['siteDomain'].'/images/emailIcon.png" width="70px" alt="">
            //                 <h3 class="fusionTxt1">Reset Password</h3> 
            //                 <div class="longLine"></div>
                            
            //                 <p class="fusionTxt3" style="text-align: left;">We received a request to reset your login password.</p>

            //                 <p class="fusionTxt3" style="text-align: left;">Enter the following temporary password:</p>

            //                 <p class="fusionTxt2">'.$verification_code.'</p>
                            
            //                 <div class="shortLine"></div>

            //                 <p class="fusionTxt3" style="text-align: left;"><b>Didn\'t request this change?</b><BR>If you didn\'t request to reset your password, contact our support team at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'">'.$emailProviderDetail['supportEmail'].'</a></p>

            //                 <p class="fusionTxt3" style="text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

            //             </div>
                         
            //             <div class="bottomDiv">

            //                 <div class="bottomImage" style="text-align: right;">
            //                     <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
            //                 </div>
            //                 <div class="bottomImage">
            //                     <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
            //                 </div>
                            
            //             </div>

            //             <div class="bottomDiv2">

            //                 <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                            
            //             </div>

            //             <div class="bottomDiv3" style="text-align: center; margin-bottom: 10px; ">
            //                 <img src="'.$emailProviderDetail['siteDomain'].'/images/email_logo_footer.png" alt="" width="40px">
            //             </div>
                         

            //         </div>
            //     </body>
            //     </html>';


                $email_template = '<!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Reset Password</title>
                        
                    </head>
                    <body>
                        <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                            <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                            <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                                <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                                <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Reset Password</h3> 
                                <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>
                                
                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">We received a request to reset your login password.</p>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Enter the following temporary password:</p>

                                <p class="fusionTxt2" style="font-size: 18px;">'.$verification_code.'</p>
                                
                                <div class="shortLine" style="display: block; width: 40px; height: 2px; background-color: #e7e7e7; clear: both; margin: 1.5rem auto;"></div>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;"><b>Didn\'t request this change?</b><br>If you didn\'t request to reset your password, contact our support team at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                            </div>
                             
                            <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                                <div class="bottomImage" style="width: 50%; float: left; text-align: right;">
                                    <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                                </div>
                                <div class="bottomImage" style="width: 50%; float: left;">
                                    <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                                </div>
                                
                            </div>

                            <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                            </div>

                            <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                                <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                            </div>
                             
                        </div>
                    </body>
                    </html>';


            return array("html"=>$email_template, 
                            "emailSubject"=>"Reset Password",
                            "emailFromName"=>$emailProviderDetail['company'], 
                            "emailAddress"=>$emailProviderDetail['username'], 
                            "emailPassword"=>$emailProviderDetail['password']);
        }

        //tuned gmail
        function getRequestFundEmail($companyName, $payer, $payee, $requestAmount, $paymentLink){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";


            // $email_template = '<!DOCTYPE html>
            //     <html lang="en">
            //     <head>
            //         <meta charset="UTF-8">
            //         <meta name="viewport" content="width=device-width, initial-scale=1.0">
            //         <title>Email Verification</title>
            //         '.$this->getStyleCss().'
            //     </head>
            //     <body>
            //         <div class="loginBlock">
            //             <img class="fusionLogo" src="'.$emailProviderDetail['siteDomain'].'/images/email_logo.png" alt="" width="150px">
            //             <div class="fusionMsgBox">
            //                 <img class="fusionEmailIcon" src="'.$emailProviderDetail['siteDomain'].'/images/emailIcon.png" width="70px" alt="">
            //                 <h3 class="fusionTxt1">Request Fund</h3> 
            //                 <div class="longLine"></div>
                            
            //                 <p class="fusionTxt3" style="text-align: left;">Hi '.$payer.',</p>

            //                 <p class="fusionTxt3" style="text-align: left;">'.$payee.' has send you a fund request.</p>

            //                 <p class="fusionTxt3" style="text-align: left;">Amount: '.$requestAmount.'</p>
                            
            //                 <p class="fusionTxt3" style="text-align: left;">To review and pay for it, please follow the link below:</p>

            //                 <p class="fusionTxt2" ><a class="fusionLinkBtn" href="'.$paymentLink.'">Review and Pay</a></p>
                            

            //                 <p class="fusionTxt3" style="text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'">'.$emailProviderDetail['supportEmail'].'</a></p>

            //                 <p class="fusionTxt3" style="text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

            //             </div>
                         
            //             <div class="bottomDiv">

            //                 <div class="bottomImage" style="text-align: right;">
            //                     <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
            //                 </div>
            //                 <div class="bottomImage">
            //                     <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
            //                 </div>
                            
            //             </div>

            //             <div class="bottomDiv2">

            //                 <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                            
            //             </div>

            //             <div class="bottomDiv3" style="text-align: center; margin-bottom: 10px; ">
            //                 <img src="'.$emailProviderDetail['siteDomain'].'/images/email_logo_footer.png" alt="" width="40px">
            //             </div>
                         

            //         </div>
            //     </body>
            //     </html>';


            $email_template = '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Request Fund</title>
                    
                </head>
                <body>
                    <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                        <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                        <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                            <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                            <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Request Fund</h3> 
                            <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Hi '.$payer.',</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">'.$payee.' has send you a fund request.</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Amount: '.$requestAmount.'</p>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">To review and pay for it, please follow the link below:</p>

                            <p class="fusionTxt2" style="font-size: 16px;"><a class="fusionLinkBtn" href="'.$paymentLink.'" style="display: block; width: 100%; background-color: '.$emailProviderDetail['themeColor'].'; color: #fff; text-decoration: none; padding: 14px; border-radius: 4px; ">Review and Pay</a></p>
                            

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                        </div>
                         
                        <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                            <div class="bottomImage" style="width: 50%; float: left; text-align: right; ">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                            </div>
                            <div class="bottomImage" style="width: 50%; float: left;">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                            </div>
                            
                        </div>

                        <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                        </div>

                        <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                            <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                        </div>
                         

                    </div>
                </body>
                </html>';

            return array("html"=>$email_template, 
                            "emailSubject"=>"Request Fund",
                            "emailFromName"=>$emailProviderDetail['company'], 
                            "emailAddress"=>$emailProviderDetail['username'], 
                            "emailPassword"=>$emailProviderDetail['password']);
        }

        //gmail tuned
        function getResellerApproveEmail($companyName, $verification_code){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";

            // $email_template = '<!DOCTYPE html>
            //     <html lang="en">
            //     <head>
            //         <meta charset="UTF-8">
            //         <meta name="viewport" content="width=device-width, initial-scale=1.0">
            //         <title>Email Verification</title>
            //         '.$this->getStyleCss().'
            //     </head>
            //     <body>
            //         <div class="loginBlock">
            //             <img class="fusionLogo" src="'.$emailProviderDetail['siteDomain'].'/images/email_logo.png" alt="" width="150px">
            //             <div class="fusionMsgBox">
            //                 <img class="fusionEmailIcon" src="'.$emailProviderDetail['siteDomain'].'/images/emailIcon.png" width="70px" alt="">
            //                 <h3 class="fusionTxt1">Welcome On Board</h3> 
            //                 <div class="longLine"></div>
                            
            //                 <p class="fusionTxt3" style="text-align: left;">Your '.$emailProviderDetail['companyName'].' Reseller account has been created successfully!</p>

            //                 <p class="fusionTxt3" style="text-align: left;">Login at <a class="mailto" href="'.$emailProviderDetail['resellerWebsite'].'">'.$emailProviderDetail['resellerWebsite'].'</a> with your username and password at below:</p>

            //                 <p class="fusionTxt2">'.$verification_code.'</p>
                            
            //                 <div class="shortLine"></div>

            //                 <p class="fusionTxt3" style="text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'">'.$emailProviderDetail['supportEmail'].'</a></p>

            //                 <p class="fusionTxt3" style="text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

            //             </div>
                         
            //             <div class="bottomDiv">

            //                 <div class="bottomImage" style="text-align: right;">
            //                     <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
            //                 </div>
            //                 <div class="bottomImage">
            //                     <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
            //                 </div>
                            
            //             </div>

            //             <div class="bottomDiv2">

            //                 <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                            
            //             </div>

            //             <div class="bottomDiv3" style="text-align: center; margin-bottom: 10px; ">
            //                 <img src="'.$emailProviderDetail['siteDomain'].'/images/email_logo_footer.png" alt="" width="40px">
            //             </div>
                         

            //         </div>
            //     </body>
            //     </html>';


            $email_template = '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Welcome Aboard</title>
                    
                </head>
                <body>
                    <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                        <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                        <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                            <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                            <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Welcome Aboard</h3> 
                            <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Your '.$emailProviderDetail['companyName'].' Reseller account has been created successfully!</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Login at <a class="mailto" href="'.$emailProviderDetail['resellerWebsite'].'" style="color:'.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['resellerWebsite'].'</a> with your username and password at below:</p>

                            <p class="fusionTxt2" style="font-size: 18px;">'.$verification_code.'</p>
                            
                            <div class="shortLine" style="display: block; width: 40px; height: 2px; background-color: #e7e7e7; clear: both; margin: 1.5rem auto;"></div>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                        </div>
                         
                        <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                            <div class="bottomImage" style="width: 50%; float: left; text-align: right; ">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                            </div>
                            <div class="bottomImage" style="width: 50%; float: left;">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                            </div>
                            
                        </div>

                        <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                        </div>

                        <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                            <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                        </div>
                         

                    </div>
                </body>
                </html>';


            return array("html"=>$email_template, 
                            "emailSubject"=>"Welcome Aboard",
                            "emailFromName"=>$emailProviderDetail['company'], 
                            "emailAddress"=>$emailProviderDetail['username'], 
                            "emailPassword"=>$emailProviderDetail['password']);
        }

        function getResellerResetPasswordEmail($companyName, $verification_code){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";


                $email_template = '<!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Reset Password</title>
                        
                    </head>
                    <body>
                        <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                            <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                            <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                                <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                                <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Reset Password</h3> 
                                <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">We received a request to reset your login password.</p>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Enter the following OTP:</p>

                                <p class="fusionTxt2" style="font-size: 18px;">'.$verification_code.'</p>
                                
                                <div class="shortLine" style="display: block; width: 40px; height: 2px; background-color: #e7e7e7; clear: both; margin: 1.5rem auto;"></div>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;"><b>Didn\'t request this change?</b><br>If you didn\'t request to reset your password, contact our support team at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                            </div>
                             
                            <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                                <div class="bottomImage" style="width: 50%; float: left; text-align: right;">
                                    <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                                </div>
                                <div class="bottomImage" style="width: 50%; float: left;">
                                    <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                                </div>
                                
                            </div>

                            <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                            </div>

                            <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                                <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                            </div>
                             
                        </div>
                    </body>
                    </html>';


            return array("html"=>$email_template, 
                            "emailSubject"=>"Reset Password",
                            "emailFromName"=>$emailProviderDetail['company'], 
                            "emailAddress"=>$emailProviderDetail['username'], 
                            "emailPassword"=>$emailProviderDetail['password']);
        }

        function getResellerRequestUsernameEmail($companyName, $verification_code){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";

            $email_template = '<!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Reset Password</title>
                        
                    </head>
                    <body>
                        <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                            <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                            <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                                <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                                <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Request Username</h3> 
                                <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">We received a request for your username.</p>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Enter the following OTP:</p>

                                <p class="fusionTxt2" style="font-size: 18px;">'.$verification_code.'</p>
                                
                                <div class="shortLine" style="display: block; width: 40px; height: 2px; background-color: #e7e7e7; clear: both; margin: 1.5rem auto;"></div>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;"><b>Didn\'t make the request?</b><br>If you didn\'t request for your username, contact our support team at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                            </div>
                             
                            <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                                <div class="bottomImage" style="width: 50%; float: left; text-align: right;">
                                    <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                                </div>
                                <div class="bottomImage" style="width: 50%; float: left;">
                                    <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                                </div>
                                
                            </div>

                            <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                            </div>

                            <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                                <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                            </div>
                             
                        </div>
                    </body>
                    </html>';
            
            return array("html"=>$email_template, 
                            "emailSubject"=>"Request Username",
                            "emailFromName"=>$emailProviderDetail['company'], 
                            "emailAddress"=>$emailProviderDetail['username'], 
                            "emailPassword"=>$emailProviderDetail['password']);

        }

        function getResellerUsernameEmail($companyName, $username){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";

            $email_template = '<!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Reset Password</title>
                        
                    </head>
                    <body>
                        <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                            <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                            <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                                <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                                <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Forgot Username</h3> 
                                <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Here\'s your username to login</p>

                                <p class="fusionTxt2" style="font-size: 18px;">'.$username.'</p>
                                
                                <div class="shortLine" style="display: block; width: 40px; height: 2px; background-color: #e7e7e7; clear: both; margin: 1.5rem auto;"></div>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;"><b>Didn\'t request this change?</b><br>If you didn\'t request for your username, contact our support team at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                            </div>
                             
                            <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                                <div class="bottomImage" style="width: 50%; float: left; text-align: right;">
                                    <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                                </div>
                                <div class="bottomImage" style="width: 50%; float: left;">
                                    <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                                </div>
                                
                            </div>

                            <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                            </div>

                            <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                                <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                            </div>
                             
                        </div>
                    </body>
                    </html>';
            
            return array("html"=>$email_template, 
                            "emailSubject"=>"Request Username",
                            "emailFromName"=>$emailProviderDetail['company'], 
                            "emailAddress"=>$emailProviderDetail['username'], 
                            "emailPassword"=>$emailProviderDetail['password']);

        }

        function getResellerRequestCommissionWithdrawalEmail($companyName, $verification_code){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";

            $email_template = '<!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Commission Withdrawal</title>
                        
                    </head>
                    <body>
                        <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                            <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                            <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                                <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                                <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Commission Withdrawal</h3> 
                                <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">We received a request to withdraw commission.</p>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Enter the following OTP:</p>

                                <p class="fusionTxt2" style="font-size: 18px;">'.$verification_code.'</p>
                                
                                <div class="shortLine" style="display: block; width: 40px; height: 2px; background-color: #e7e7e7; clear: both; margin: 1.5rem auto;"></div>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;"><b>Didn\'t make the request?</b><br>If you didn\'t request to withdraw commission, contact our support team at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                                <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                            </div>
                             
                            <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                                <div class="bottomImage" style="width: 50%; float: left; text-align: right;">
                                    <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                                </div>
                                <div class="bottomImage" style="width: 50%; float: left;">
                                    <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                                </div>
                                
                            </div>

                            <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                            </div>

                            <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                                <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                            </div>
                             
                        </div>
                    </body>
                    </html>';
            
            return array("html"=>$email_template, 
                            "emailSubject"=>"Withdraw Commission OTP",
                            "emailFromName"=>$emailProviderDetail['company'], 
                            "emailAddress"=>$emailProviderDetail['username'], 
                            "emailPassword"=>$emailProviderDetail['password']);
        }

        function getSendFundEmail($companyName, $sendFundParam){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";

            $sender_name = $sendFundParam['sender_name'];
            $receiver_name = $sendFundParam['receiver_name'];
            $amount = $sendFundParam['amount'];
            $symbol = $sendFundParam['symbol'];
            $description = $sendFundParam['description'];
            $redeem_code = $sendFundParam['redeem_code'];

            $email_template = '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Fund Redemption</title>
                    
                </head>
                <body>
                    <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                        <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                        <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                            <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                            <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Fund Redemption</h3> 
                            <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Hi '.$receiver_name.',</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">'.$sender_name.' has sent fund to you via '.$emailProviderDetail['companyName'].'.</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Amount: '.$amount .' '. strtoupper($symbol).'</p>

                            <p class="fusionTxt3" style="font-size: 14px; margin: 20px 0 15px 0; text-align: left;">Description: '.$description.'</p>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Please follow the link and redeem with the PIN below.</p>

                            <p class="fusionTxt3" style="font-size: 30px; line-height: 1.3; margin: 20px 0 15px 0; text-align: center;">'.$redeem_code.'</p>

                            <p class="fusionTxt2" style="font-size: 16px;"><a class="fusionLinkBtn" href="'.$emailProviderDetail['siteDomain'].'/redeemFund.php" style="display: block; width: 100%; background-color: '.$emailProviderDetail['themeColor'].'; color: #fff; text-decoration: none; padding: 14px; border-radius: 4px; ">Redeem Now</a></p>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                        </div>
                        
                        <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                            <div class="bottomImage" style="width: 50%; float: left; text-align: right; ">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                            </div>
                            <div class="bottomImage" style="width: 50%; float: left;">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                            </div>
                            
                        </div>

                        <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                        </div>

                        <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                            <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                        </div>
                        

                    </div>
                </body>
                </html>';

                return array("html"=>$email_template, 
                                "emailSubject"=>"Fund Redemption",
                                "emailFromName"=>$emailProviderDetail['company'], 
                                "emailAddress"=>$emailProviderDetail['username'], 
                                "emailPassword"=>$emailProviderDetail['password']);

        }
        

        function getSendRedeemEmail($companyName, $sendFundParam){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";

            $sender_name = $sendFundParam['sender_name'];
            $receiver_name = $sendFundParam['receiver_name'];
            $amount = $sendFundParam['amount'];
            $symbol = $sendFundParam['symbol'];
            $description = $sendFundParam['description'];
            $redeem_code = $sendFundParam['redeem_code'];

            $email_template = '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Redeem Code</title>
                    
                </head>
                <body>
                    <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                        <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                        <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                            <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                            <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Redeem Code</h3> 
                            <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Hi '.$sender_name.',</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">'.$receiver_name.' has claimed your code ('.$redeem_code.') via '.$emailProviderDetail['companyName'].'.</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Amount: '.$amount .' '. strtoupper($symbol).'</p>

                            <p class="fusionTxt3" style="font-size: 14px; margin: 20px 0 15px 0; text-align: left;">Description: '.$description.'</p>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Please log back in your account and apply further actions.</p>

                            <p class="fusionTxt3" style="font-size: 30px; line-height: 1.3; margin: 20px 0 15px 0; text-align: center;"> </p>

                            <p class="fusionTxt2" style="font-size: 16px;"><a class="fusionLinkBtn" href="'.$emailProviderDetail['siteDomain'].'/login.php" style="display: block; width: 100%; background-color: '.$emailProviderDetail['themeColor'].'; color: #fff; text-decoration: none; padding: 14px; border-radius: 4px; ">Release escrow now</a></p>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                        </div>
                        
                        <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                            <div class="bottomImage" style="width: 50%; float: left; text-align: right; ">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                            </div>
                            <div class="bottomImage" style="width: 50%; float: left;">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                            </div>
                            
                        </div>

                        <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                        </div>

                        <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                            <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                        </div>
                        

                    </div>
                </body>
                </html>';

                return array("html"=>$email_template, 
                                "emailSubject"=>"Redeem Code",
                                "emailFromName"=>$emailProviderDetail['company'], 
                                "emailAddress"=>$emailProviderDetail['username'], 
                                "emailPassword"=>$emailProviderDetail['password']);

        }

        function getSendEscrowRedeemEmail($companyName, $sendFundParam){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";

            $sender_name = $sendFundParam['sender_name'];
            $receiver_name = $sendFundParam['receiver_name'];
            $amount = $sendFundParam['amount'];
            $symbol = $sendFundParam['symbol'];
            $description = $sendFundParam['description'];
            $redeem_code = $sendFundParam['redeem_code'];

            $email_template = '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Escrow Redeem Code</title>
                    
                </head>
                <body>
                    <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                        <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                        <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                            <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                            <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Escrow Redeem Code</h3> 
                            <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Hi '.$sender_name.',</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">'.$receiver_name.' has claimed your escrow enabled redeem code via '.$emailProviderDetail['companyName'].'.</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Amount: '.$amount .' '. strtoupper($symbol).'</p>

                            <p class="fusionTxt3" style="font-size: 14px; margin: 20px 0 15px 0; text-align: left;">Description: '.$description.'</p>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Please log back in your account and apply further actions.</p>

                            <p class="fusionTxt3" style="font-size: 30px; line-height: 1.3; margin: 20px 0 15px 0; text-align: center;"> </p>

                            <p class="fusionTxt2" style="font-size: 16px;"><a class="fusionLinkBtn" href="'.$emailProviderDetail['siteDomain'].'/login.php" style="display: block; width: 100%; background-color: '.$emailProviderDetail['themeColor'].'; color: #fff; text-decoration: none; padding: 14px; border-radius: 4px; ">Release escrow now</a></p>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                        </div>
                        
                        <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                            <div class="bottomImage" style="width: 50%; float: left; text-align: right; ">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                            </div>
                            <div class="bottomImage" style="width: 50%; float: left;">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                            </div>
                            
                        </div>

                        <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                        </div>

                        <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                            <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                        </div>
                        

                    </div>
                </body>
                </html>';

                return array("html"=>$email_template, 
                                "emailSubject"=>"Escrow Redeem Code",
                                "emailFromName"=>$emailProviderDetail['company'], 
                                "emailAddress"=>$emailProviderDetail['username'], 
                                "emailPassword"=>$emailProviderDetail['password']);

        }

        function getSendReleaseEmail($companyName, $sendFundParam){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";

            $sender_name = $sendFundParam['sender_name'];
            $receiver_name = $sendFundParam['receiver_name'];
            $amount = $sendFundParam['amount'];
            $symbol = $sendFundParam['symbol'];            
            $id = $sendFundParam['id'];            

            $email_template = '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Escrow Released</title>
                    
                </head>
                <body>
                    <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                        <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                        <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                            <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                            <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Escrow Released</h3> 
                            <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Hi '.$receiver_name.',</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">'.trim($sender_name).' has released your escrow fund via '.$emailProviderDetail['companyName'].'.</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Amount: '.$amount .' '. strtoupper($symbol).'</p>

                            <p class="fusionTxt3" style="font-size: 14px; margin: 20px 0 15px 0; text-align: left;"> </p>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;"></p>

                            <p class="fusionTxt3" style="font-size: 30px; line-height: 1.3; margin: 20px 0 15px 0; text-align: center;"> </p>

                            <p class="fusionTxt2" style="font-size: 16px;"><a class="fusionLinkBtn" href="'.$emailProviderDetail['siteDomain'].'/login.php" style="display: block; width: 100%; background-color: '.$emailProviderDetail['themeColor'].'; color: #fff; text-decoration: none; padding: 14px; border-radius: 4px; ">Log In Now</a></p>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                        </div>
                        
                        <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                            <div class="bottomImage" style="width: 50%; float: left; text-align: right; ">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                            </div>
                            <div class="bottomImage" style="width: 50%; float: left;">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                            </div>
                            
                        </div>

                        <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                        </div>

                        <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                            <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                        </div>
                        

                    </div>
                </body>
                </html>';

                return array("html"=>$email_template, 
                                "emailSubject"=>"Escrow Released ".$id,
                                "emailFromName"=>$emailProviderDetail['company'], 
                                "emailAddress"=>$emailProviderDetail['username'], 
                                "emailPassword"=>$emailProviderDetail['password']);

        }

        function getReceiveFundEmailPG($companyName, $receiveFundParam){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";

            $sender_name = $receiveFundParam['sender_name'];
            $receiver_name = $receiveFundParam['receiver_name'];
            $amount = $receiveFundParam['amount'];
            $symbol = $receiveFundParam['symbol'];
            $description = $receiveFundParam['description'];

             $email_template = '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Fund Received</title>
                    
                </head>
                <body>
                    <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                        <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                        <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                            <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                            <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Fund Received</h3> 
                            <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Hi '.$receiver_name.',</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">'.$sender_name.' sent fund to you via NuxPay.</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Amount: '.$amount .' '. strtoupper($symbol).'</p>

                            <p class="fusionTxt3" style="font-size: 14px; margin: 20px 0 15px 0; text-align: left;">Description: '.$description.'</p>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Login to find out more details.</p>

                            <p class="fusionTxt2" style="font-size: 16px;"><a class="fusionLinkBtn" href="'.$emailProviderDetail['siteDomain'].'/login.php" style="display: block; width: 100%; background-color: '.$emailProviderDetail['themeColor'].'; color: #fff; text-decoration: none; padding: 14px; border-radius: 4px; ">Login Now</a></p>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                        </div>
                        
                        <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                            <div class="bottomImage" style="width: 50%; float: left; text-align: right; ">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                            </div>
                            <div class="bottomImage" style="width: 50%; float: left;">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                            </div>
                            
                        </div>

                        <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                        </div>

                        <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                            <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                        </div>
                        
                    </div>
                </body>
                </html>';

                return array("html"=>$email_template, 
                                "emailSubject"=>"Fund Received",
                                "emailFromName"=>$emailProviderDetail['company'], 
                                "emailAddress"=>$emailProviderDetail['username'], 
                                "emailPassword"=>$emailProviderDetail['password']);

        }
        function getReceiveFundEmailBC($companyName, $receiveFundParam){
            global $config;
            $emailProviderDetail = $this->getEmailProvider($companyName);
            $hideFlag = $config['isWhitelabel'] ? "display:none;" : "display:block;";

            $sender_name = $receiveFundParam['sender_name'];
            $receiver_name = $receiveFundParam['receiver_name'];
            $amount = $receiveFundParam['amount'];
            $symbol = $receiveFundParam['symbol'];
            $description = $receiveFundParam['description'];

             $email_template = '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Fund Received</title>
                    
                </head>
                <body>
                    <div class="loginBlock" style="display: block; width: 500px; padding: 3rem 3rem; max-width: 100%; background-color: #f4f7fa; background-size: cover; background-repeat: no-repeat; background-position: right 15%; color: #141414; font-family: Arial, Helvetica, sans-serif;">
                        <img class="fusionLogo" src="'.$emailProviderDetail['imageUrlPath'].'/email_logo.png" alt="" width="150px" style="display: block; margin: 0 auto;">
                        <div class="fusionMsgBox" style="background-color: #fff; border-radius: 8px; margin-top: 2rem; text-align: center; padding: 1rem 10%; box-shadow: 0 0 20px -10px #ccc;">
                            <img class="fusionEmailIcon" src="'.$emailProviderDetail['imageUrlPath'].'/emailIcon.png" width="70px" alt="" style="display: block; margin: 1.5rem auto;">
                            <h3 class="fusionTxt1" style="font-size: 18px; color: #48545c;">Fund Received</h3> 
                            <div class="longLine" style="display: block; width: 100%; height: 2px; background-color: #e7e7e7; clear: both; margin: 2rem auto;"></div>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Hi '.$receiver_name.',</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Someone sent fund to you via NuxPay.</p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Amount: '.$amount .' '. strtoupper($symbol).'</p>

                            <p class="fusionTxt3" style="font-size: 14px; margin: 20px 0 15px 0; text-align: left;">Description: '.$description.'</p>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Login to find out more details.</p>

                            <p class="fusionTxt2" style="font-size: 16px;"><a class="fusionLinkBtn" href="'.$emailProviderDetail['siteDomain'].'/login.php" style="display: block; width: 100%; background-color: '.$emailProviderDetail['themeColor'].'; color: #fff; text-decoration: none; padding: 14px; border-radius: 4px; ">Login Now</a></p>
                            
                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">If you have any further question or issues, don\'t forget to consult our support team anytime at <a class="mailto" href="mailto:'.$emailProviderDetail['supportEmail'].'" style="color: '.$emailProviderDetail['themeColor'].';">'.$emailProviderDetail['supportEmail'].'</a></p>

                            <p class="fusionTxt3" style="font-size: 14px; line-height: 1.3; margin: 20px 0 15px 0; text-align: left;">Have a nice day!<br>'.$emailProviderDetail['companyName'].' Team</p>

                        </div>
                        
                        <div class="bottomDiv" style="width: 500px; margin-top: 20px; '.$hideFlag.'">

                            <div class="bottomImage" style="width: 50%; float: left; text-align: right; ">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/in.png" alt="" style="width: 20px; margin-right: 2px;">
                            </div>
                            <div class="bottomImage" style="width: 50%; float: left;">
                                <img src="'.$emailProviderDetail['siteDomain'].'/images/fb.png" alt="" style="width: 20px; margin-left: 2px;">
                            </div>
                            
                        </div>

                        <div class="bottomDiv2" style="width: 500px; margin-top: 50px; text-align: center;">
                                <p class="fusionTxt4">'.str_replace("\n", "<BR>", $emailProviderDetail['companyAddress']).'</p>
                                
                        </div>

                        <div class="bottomDiv3" style="width: 500px; text-align: center; margin-bottom: 10px; '.$hideFlag.'">
                            <img src="'.$emailProviderDetail['imageUrlPath'].'/email_logo_footer.png" alt="" width="40px">
                        </div>
                        
                    </div>
                </body>
                </html>';

                return array("html"=>$email_template, 
                                "emailSubject"=>"Fund Received",
                                "emailFromName"=>$emailProviderDetail['company'], 
                                "emailAddress"=>$emailProviderDetail['username'], 
                                "emailPassword"=>$emailProviderDetail['password']);

        }  
        
    }

?>