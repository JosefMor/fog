<?php

/* 
# Usage : see use.php

# Credits : 
* inspired by: http://www.electrictoolbox.com/php-imap-message-body-attachments/ */

class Fog {

    public $hostname ;
    public $username ;
    public $password ;
    public $recipientMailAddress ;
    public $recipientName ;
    public $senderName ;
    public $senderUsername ;
    public $senderPassword ;
    public $criteria;
    
    static function flattenParts($messageParts, $flattenedParts = array(), $prefix = '', $index = 1, $fullPrefix = true) {

	foreach($messageParts as $part) {
		$flattenedParts[$prefix.$index] = $part;
		if(isset($part->parts)) {
			if($part->type == 2) {
				$flattenedParts = self::flattenParts($part->parts, $flattenedParts, $prefix.$index.'.', 0, false);
			}
			elseif($fullPrefix) {
				$flattenedParts = self::flattenParts($part->parts, $flattenedParts, $prefix.$index.'.');
			}
			else {
				$flattenedParts = self::flattenParts($part->parts, $flattenedParts, $prefix);
			}
			unset($flattenedParts[$prefix.$index]->parts);
		}
		$index++;
	}

	return $flattenedParts;
			
    }
    
    static function decode ($coding, $body)
    {
        if ($coding == 0) {
            return quoted_printable_decode($body);
        }
        if ($coding == 1) {
            return imap_8bit($body);
        }
        if ($coding == 2) {
            return imap_binary($body);
        }
        if ($coding == 3) {
            return imap_base64($body);
        }
        if ($coding == 4) {
            return quoted_printable_decode($body);
        }
        if ($coding == 5) {
            return $body;
        }
        return $body;
    }
    
    // hr = Human Readable
    static function hrEncode($name = "", array $mailInfos) {
        $listifiedMailInfos = array();
        foreach ($mailInfos as $mailInfo) {
            $listifiedMailInfos[] = $mailInfo->mailbox."@".$mailInfo->host.(isset($mailInfo->personal)? " (".$mailInfo->personal.")":"");
        }
        if (count($listifiedMailInfos) > 0) {
            $hrMailInfo = implode(", ", $listifiedMailInfos);
            return $name.$hrMailInfo."\r\n";
        }
        return "";
    }
    

    public function forward() {
        $box = imap_open($this->hostname,$this->username,$this->password) or die('Cannot connect to Gmail: ' . imap_last_error());
        
        /* grab emails */
        $emails = imap_search($box, $this->criteria);
        /* if emails are returned, cycle through each... */
        if($emails) {
          
            echo "line = ".__LINE__."pour ".substr($this->username,0,4).", count(emails) vaut".count($emails);
          
            /* put the newest emails on top */
            rsort($emails);
            /* for every email... */
        
            foreach($emails as $email_number) {
                /* TODO : create a "message" class to manage all those variables */
                $body = "";
                $bodyIsHtml = true;
                /* get information specific to this email */
                $structure = imap_fetchstructure($box, $email_number); 
                $flattenedStructure = self::flattenParts($structure->parts);
                if (!isset($structure->parts)) {
                    echo "line = ".__LINE__." mail sans structure.";
                    $body = imap_body($box, $email_number);
                    $bodyIsHtml = false;
                    $coding = $structure->encoding;
                } else {
                    $parts = array("1.1.2", "1.2", "1", "2");
                    foreach ($parts as $part) {
                        $body=imap_fetchbody($box, $email_number, $part);
                        if (strlen($body) > 0) {
                            break;
                        }
                    }
                    if ($part == "1") {
                        if ($flattenedStructure[$part]->subtype != "HTML") {
                            $bodyIsHtml = false;
                        }
                    }
                    $coding = $flattenedStructure[$part]->encoding;
                }
                if (strlen($body) == 0) {
                    $body = "Le corps du message est vide."; 
                    $coding = 5;
                }
                
                $imapHeaderInfo = imap_headerinfo($box, $email_number);
            
                            
    
            /*    "to" : un tableau d'objets issus de la ligne to: avec les propriétés suivantes : personal, adl, mailbox, et host */
                $toList = "";
                $ccList = "";
                $bccList = "";
                        
                if (isset($imapHeaderInfo->to)) {
                    $toList = self::hrEncode("Destinataires : ", $imapHeaderInfo->to) ;
                }
    
                if (isset($imapHeaderInfo->cc)) {
                    $ccList = self::hrEncode("Copie : ", $imapHeaderInfo->cc) ;
                }
    
                if (isset($imapHeaderInfo->bcc)) {
                    $bccList = self::hrEncode("Copie cachée : ", $imapHeaderInfo->bcc) ;
                }
    
                $overview = imap_fetch_overview($box,$email_number);
                $date = "Date : ".$overview[0]->date."\r\n";    
                $separator = "----------------------------------------------------\r\n";
                
                $from = "De : ".($this->username)."\r\n";
                $head = $separator.$from.$toList.$ccList.$bccList.$date.$separator."\r\n";
                $mail = new PHPMailer();
                
                $mail->CharSet = 'UTF-8';
                $mail->Mailer = 'smtp';
                $mail->Host = 'smtp.gmail.com';
                $mail->Port = 465;
                $mail->SMTPSecure = "ssl";
                $mail->SMTPAuth = true;
                $mail->From = $this->senderUsername;
                $mail->FromName = $this->senderName;
                $mail->Username = $this->senderUsername;
                $mail->Password = $this->senderPassword;
                
                $mail->Subject = $overview[0]->subject;
                if ($bodyIsHtml) {
                    $mail->MsgHTML(nl2br($head).self::decode($coding, $body));
                } else {
                    $mail->Body = $head.self::decode($coding, $body);
                }
    
                if (filter_var($this->recipientMailAddress, FILTER_VALIDATE_EMAIL) !== false) {
    
                    $mail->AddAddress($this->recipientMailAddress, $this->recipientName);
                    if ($mail->Send()) {
                        // *Answered* used as a marker
                        imap_setflag_full($box, $email_number, "\\Answered");
                        echo "message envoyé";
                    } else {
                        echo "envoi en échec";
                    }
                } else {
                    echo $mail->Body;
                }
            }
        } else {
          echo "line ".__LINE__.", pour ".substr($this->username,0,4).", emails vaut ";
          var_dump($emails);
        }
        
        /* close the connection */
        imap_close($box);
    }

}