<?php

include_once(__DIR__."/externals/phpmailer/class.phpmailer.php");
include_once(__DIR__."/fog.php");

$fog = new Fog();
$fog->hostname = "{imap.gmail.com:993/ssl}[Gmail]/Messages envoy&AOk-s";
$fog->username = "";
$fog->password = "";
$fog->recipientMailAddress = "";
$fog->recipientName = "";
$fog->senderName = "";
$fog->senderUsername = "";
$fog->senderPassword = "";
$fog->criteria = 'UNANSWERED SINCE "22 October 2012"';
$fog->forward();
