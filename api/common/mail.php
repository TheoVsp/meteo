<?php
require "vendor/autoload.php";

use R2n\R2nMail;

/**
 * Store logger
 */
class GlobalMail{
    public static R2nMail $mail;
}

GlobalMail::$mail = new R2nMail(GlobalLogger::$logger,GlobalEnv::$env_mail_smtp_host, (int)GlobalEnv::$env_mail_smtp_port);