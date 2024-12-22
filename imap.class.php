<?php
class IMAP {
    protected $constring;
    
    protected $connection;
    
    protected $extensions = array( "txt", "jpg", "jpeg", "png", "xls", "doc", "docx", "xlsx", "zip", "rar", "pdf" ); 

    protected $mimeType = array( "text", "multipart", "message", "application", "audio", "image", "video", "other" );
    
    protected $encodingType = array( "7bit", "8bit", "binary", "base64", "quoted-printable", "other" );

    public function __construct($hostname,$port,$service = 'imap',$ssl_valid = false){
        if(!function_exists('imap_open')){
            throw new Exception('IMAP is not enabled!');
        }
        $params = ($ssl_valid == true ? "{$service}/ssl/validate-cert" : "{$service}/ssl/novalidate-cert");
        $this->constring = sprintf("{%s:%d/%s}",$hostname,$port,$params);
    }

    public function authenticate($username,$password){
        $this->connection = imap_open($this->constring,$username,$password);
    }

    public function getMailboxes(){
        $boxes = imap_list($this->connection,$this->constring,"*");
        $count = count($boxes);
        for($i=0;$i<$count;$i++){
            $boxes[$i] = str_replace($this->constring, "", $boxes[$i]);
        }
        return $boxes;
    }

    public function getMails($mailbox = 'INBOX'){
        imap_reopen($this->connection, $this->constring.$mailbox);
        return imap_search($this->connection, 'ALL');
    }

    public function getOverview($mail){
        return imap_fetch_overview($this->connection, $mail);
    }

    public function getHeaderInfo($mail){
        return imap_headerinfo($this->connection, $mail);
    }

    public function getFromAddress($mail){
        $headers = $this->getHeaderInfo($mail);
        return $headers->from[0]->mailbox.'@'.$headers->from[0]->host;
    }

    public function getToAddress($mail){
        $headers = $this->getHeaderInfo($mail);
        return $headers->to[0]->mailbox.'@'.$headers->to[0]->host;
    }

    public function getSubject($mail){
        $overview = $this->getOverview($mail);
        return $overview[0]->subject;
    }

    public function hasTicketId($mail){
        preg_match('!\d+!', $this->getSubject($mail), $results);
        return isset($results[0]);
    }

    public function getTicketId($mail){
        preg_match('!\d+!', $this->getSubject($mail), $results);
        return isset($results[0]) && $results != 0 ? $results[0] : 0;
    }

    public function getStructure($mail){
        return imap_fetchstructure($this->connection, $mail);
    }

    public function parseMailStructure($mail,$type = "TEXT/HTML"){
        $parsed = array();
        $structure = $this->getStructure($mail);
        $parts = $structure->parts;
        $psize = count($parts);
        for($i=0; $i<$psize; $i++){
            $parsed[$i] = array();
            $parsed[$i]['pid'] = $i + 1;
            $part = $parts[$i];
            
            $part->type = ($part->type == "") ? 0 : $part->type;
            $part->encoding = ($part->encoding == "") ? 0 : $part->encoding;

            $parsed[$i]['type'] = $this->mimeType[$part->type]."/".strtolower($part->subtype);
            
            $parsed[$i]['encoding'] = $this->encodingType[$part->encoding];

            $parsed[$i]['size'] = isset($part->bytes) ? strtolower($part->bytes) : 0;

            $parsed[$i]['disposition'] = isset($part->disposition) ? strtolower($part->disposition) : 0;
            
            $paramCount = isset($part->parameters) && !empty($part->parameters) ? count($part->parameters) : 0;
            $dparamCount = isset($part->dparameters) && !empty($part->dparameters) ? count($part->dparameters) : 0;

            for($x=0; $x < $paramCount; $x++){
                $parsed[$i][strtolower($part->parameters[$x]->attribute)] = $part->parameters[$x]->value;
            }

            for($x=1; $x < $dparamCount; $x++){
                $parsed[$i][strtolower($part->dparameters[$x]->attribute)] = $part->dparameters[$x]->value;
            }
            
        }
        return $parsed;
    }

    public function hasAllowedExtension($name){
        $exploded_name = explode(".", strtolower($name));
        $extension = end($exploded_name);
        if(!in_array($extension, $this->extensions)){
            return false;
        }
        return true;
    }

    public function getAttachmentList($mail){
        $files = [];
        $sections = $this->parseMailStructure($mail);
        $sCount = count($sections);
        for($i=0;$i<$sCount;$i++){
            if(isset($sections[$i]['filename']) || isset($sections[$i]['name'])){
                $files[] = $sections[$i];
            }
        }
        return $files;
    }

    public function getAttachments($mail,$location=false,$size=false){
        if(!$location){
            throw new Exception("Error Processing Request", 1);
        } elseif (!is_dir($location)) {
            mkdir($location);
            chmod($location, 775);
        }
        if($size != false){
            $size = $size*1024*1024;
        }
        $list = $this->getAttachmentList($mail);
        if(count($list)>=1){
            $array = [];
            $count = count($list);
            for($a=0;$a<$count;$a++){
                $filename = isset($list[$a]['filename']) ?  $list[$a]['filename'] : $list[$a]['name'];
                if($this->hasAllowedExtension($filename)){
                    if((int)$list[$a]['size'] <= (int)$size){
                        $exploded_name = explode(".", $filename);
                        $extension = end($exploded_name);
                        $dbname = md5($filename.time().microtime().rand(99999,10000)).".".$extension;
                        $data = $this->getBody($mail,$list[$a]['pid']);
                        if($list[$a]['encoding'] == "base64"){
                            $data = imap_base64($data);
                        }
                        $fp = fopen($location.$dbname, "w");
                        fwrite($fp, $data);
                        fclose($fp);
                        $array[$a] = [
                            "filename" => $dbname,
                            "name" => $filename,
                            "size" => $list[$a]['size'],
                            "ext" => $extension,
                            "location" => $location."/".$dbname
                        ];
                    }
                }
            }
            return isset($array) ? $array : [];
        }
    }

    public function getMimeType($structure){
        return $structure->subtype ? $this->mimeType[(int)$structure->type]."/".$structure->subtype : "TEXT/PLAIN";
    }

    public function getBody($mail,$partno){
        return imap_fetchbody($this->connection, $mail, $partno);
    }

    public function getPartByType($mail,$type = 'TEXT/PLAIN', $structure = false, $partno = false){
        $structure = !empty($structure) ? $structure : $this->getStructure($mail);
        if($structure->type == 1){
            while(list($index, $sub_structure) = each($structure->parts)){
                $prefix = isset($partno) && $partno != 0 ? $partno : "";
                $message = $this->getPartByType($mail,$type,$sub_structure,$prefix.$index+1);
                return $message ? $message : false;
            }
        } else {
            for($i=0;$i<count($structure->parameters);$i++){
                if($structure->parameters[$i]->attribute == "CHARSET"){
                    $charset = $structure->parameters[$i]->value == "UTF-8" ? "" : $structure->parameters[$i]->value;
                }
            }
            if(strtoupper($type) == strtoupper($this->getMimeType($structure))){
                $partno = isset($partno) && $partno != 0 ? $partno : 1;
                $message = $this->getBody($mail,$partno);
                $message = $structure->encoding == 3 ? imap_base64($message) : $structure->encoding == 4 ? imap_qprint($message) : $message;
                return isset($message) && !empty($message) ? $message : "No results!\n";
            }
        }
    }
    
    public function getHtmlMessage($mail){
        return $this->getPartByType($mail,'TEXT/HTML');
    }

    public function getTextMessage($mail){
        return $this->getPartByType($mail,'TEXT/PLAIN');
    }

    public function deleteMail($mail){
        return imap_delete($this->connection, $mail);
    }

    public function __destruct(){
        imap_close($this->connection);
    }
}
