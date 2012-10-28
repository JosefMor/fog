<?php

class Fog {

    public $hostname ;
    public $username ;
    public $password ;
    public $recipientMailAddress ;
    public $recipientName ;
    
    
    private function flattenParts($messageParts, $flattenedParts = array(), $prefix = '', $index = 1, $fullPrefix = true) {

	foreach($messageParts as $part) {
		$flattenedParts[$prefix.$index] = $part;
		if(isset($part->parts)) {
			if($part->type == 2) {
				$flattenedParts = flattenParts($part->parts, $flattenedParts, $prefix.$index.'.', 0, false);
			}
			elseif($fullPrefix) {
				$flattenedParts = flattenParts($part->parts, $flattenedParts, $prefix.$index.'.');
			}
			else {
				$flattenedParts = flattenParts($part->parts, $flattenedParts, $prefix);
			}
			unset($flattenedParts[$prefix.$index]->parts);
		}
		$index++;
	}

	return $flattenedParts;
			
    }

    public function forward() {
        $box = imap_open($this->hostname,$this->username,$this->password) or die('Cannot connect to Gmail: ' . imap_last_error());
        
        /* grab emails */
        $emails = imap_search($box,'UNANSWERED SINCE "22 October 2012"');
        /* if emails are returned, cycle through each... */
        if($emails) {
          
          echo "line = ".__LINE__."pour ".substr($this->username,0,4).", count(emails) vaut".count($emails);
          
          /* put the newest emails on top */
          rsort($emails);
          /* for every email... */
        
        foreach($emails as $email_number) {
            $body = "";
            $bodyIsHtml = true;
            /* get information specific to this email */
            $structure = imap_fetchstructure($box, $email_number); 
            if (!isset($structure->parts)) {
                echo "line = ".__LINE__." mail sans structure.";
                $body = imap_body($box, $email_number);
                $bodyIsHtml = false;
                $coding = $structure->encoding;
            } else {
        
                $part = "1.1.2";
                $body=imap_fetchbody($box, $email_number, $part); 
                if (strlen($body) == 0) {
                    $part = "1.2";
                    $body=imap_fetchbody($box, $email_number, $part); 
                }
                if (strlen($body) == 0) {
                    $part = "2";
                    $body=imap_fetchbody($box, $email_number, $part); 
                }
                if (strlen($body) == 0) {
                    $part = "1";
                    $body=imap_fetchbody($box, $email_number, $part); 
                }
                $flattenedStructure = flattenParts($structure->parts);
                $coding = $flattenedStructure[$part]->encoding;
            }
            if (strlen($body) == 0) {
                $body="Le corps du message est vide."; 
                $coding = 5;
            }
            
            if ($coding == 0) 
            { 
                $body = quoted_printable_decode($body);
            } 
            elseif ($coding == 1) 
            { 
                $body = imap_8bit($body); 
            } 
            elseif ($coding == 2) 
            { 
                $body = imap_binary($body); 
            } 
            elseif ($coding == 3) 
            { 
                $body = imap_base64($body); 
            } 
            elseif ($coding == 4) 
            { 
                $body = quoted_printable_decode($body); 
            } 
            elseif ($coding == 5) 
            { 
                $body = $body; 
            } 
            
            
            $imapHeaderInfo = imap_headerinfo($box, $email_number);
        
        /*    "to" : un tableau d'objets issus de la ligne to: avec les propriétés suivantes : personal, adl, mailbox, et host */
            $to = array();
            $cc = array();
            $bcc = array();
            $toList = "";
            $ccList = "";
            $bccList = "";
                    
            if (isset($imapHeaderInfo->to)) {
                foreach ($imapHeaderInfo->to as $mailInfo) {
                    $to[] = $mailInfo->mailbox."@".$mailInfo->host.(isset($mailInfo->personal)? " (".$mailInfo->personal.")":"");
                }
            }
            if (isset($imapHeaderInfo->cc)) {
                foreach ($imapHeaderInfo->cc as $mailInfo) {
                    $cc[] = $mailInfo->mailbox."@".$mailInfo->host.(isset($mailInfo->personal)? " (".$mailInfo->personal.")":"");
                }
            }
        
            if (isset($imapHeaderInfo->bcc)) {
                foreach ($imapHeaderInfo->bcc as $mailInfo) {
                    $bcc[] = $mailInfo->mailbox."@".$mailInfo->host.(isset($mailInfo->personal)? " (".$mailInfo->personal.")":"");
                }
            }
        
            if (count($to) > 0) {
                $toList = implode(", ", $to);
                $toList = "Destinataire : ".($toList)."<br/>";
            }
            if (count($cc) > 0) {
                $ccList = implode(", ", $cc);
                $ccList = "Copie : ".($ccList)."<br/>";
        
            }
            if (count($bcc) > 0) {
                $bccList = implode(", ", $bcc);
                $bccList = "Copie cachée : ".($bccList)."<br/>";
            }
    
            $overview = imap_fetch_overview($box,$email_number);
            $date = $overview[0]->date."<br/>";    
            $separator = "----------------------------------------------------"."<br/><br/>";
            
            $from = "Expéditeur : ".($this->username)."<br/>";
            $body = $from.$toList.$ccList.$bccList.$date.$separator.$body;
            echo $body;
            $mail = new PHPMailer();
            $mail->Subject = $overview[0]->subject;
            if ($bodyIsHtml) {
                $mail->MsgHTML($body);
            } else {
                $mail->Body = $body;
            }
            $mail->AddAddress($this->$recipientMailAddress, $this->$recipientName);
            if ($mail->Send()) {
                // *Answered* used as a marker
                imap_setflag_full($box, $email_number, "\\Answered");
                echo "message envoyé";
            } else {
                echo "envoi en échec";
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

//Usage

include_once(__DIR__."/PHPMailer/class.phpmailer.php");

$fog = new Fog();
$fog->hostname = "{imap.gmail.com:993/ssl}[Gmail]/Messages envoy&AOk-s";
$fog->username = "username@gmail.com";
$fog->password = "password";
$fog->recipientMailAddress = "recipient@gmail.com";
$fog->recipientName = "Recipient Name";
$fog->forward();
