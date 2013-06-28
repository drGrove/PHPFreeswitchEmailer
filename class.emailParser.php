<?php
/**
 * Basic Email Parser
 * @package EmailParser
 */
/**
 * Extracts header and body from Raw Email following 
 * RFC 822
 * http://www.w3.org/Protocols/rfc822/ 
 *
 * @version V1.0
 * @author Daniel Grove
 */
class Email {
    /**
     * Associative array of all Email headers
     * @var associative array
     */
    protected $headers = array();

    /**
     * Raw email content
     * @var string 
     */
    private $emailRawContent;

    /**
     * Associative array of headers
     * @var associative array
     */
    public $rawFields;

    /**
     * Array of stings from the body of the email
     * @var array of strings
     */
    protected $rawBodyLines;

    /**
     * Constructor
     * Takes in a raw email and parses the headers and raw body
     * @param string $emailStr
     */
    public function __construct($emailStr) {
        $this->emailRawContent = $emailStr;
        $this->getHeadersAndRawBody();
    }

    /**
     *
     * function to parse the headers and raw body from a raw email
     */
    private function getHeadersAndRawBody() {
        $lines = preg_split("/(\r?\n|\r)/", $this->emailRawContent);
        $currentHeader = '';

        $x = 0;
        foreach($lines as $line) {
            if(self::isNewLine($line)) {
            $this->rawBodyLines = array_slice($lines, $x);
            break;
        }
        if($this->isLineStartingWithPrintableChar($line)) {
            preg_match('/([^:]+): ?(.*)$/', $line, $matches);
            $newHeader = strtolower($matches[1]);
            $values = $matches[2];
            $this->rawFields[$newHeader] = $values;
            $currentHeader = $newHeader;
        } else {
            if($currentHeader) {
            $this->rawFields[$currentHeader] .= substr($line, 1);
            }
        }
        $x++;
        }
    }

    public function getContentType() {
      if(!array_key_exists('content-type', $this->rawFields)) {
        throw new Exception('Could not find Content-Type');
      }
      return $this->rawFields['content-type'];
    }

    /**
     * Get the Boundries of the Email
     * @return string 
     */
    public function getBoundry() {
      if(!array_key_exists('content-type', $this->rawFields)) {
        throw new Exception('Cound not find Boundry in Email');
      } else {
        $boundry = split(";", $this->rawFields['content-type']);
        $boundry = split("=", $boundry[1]);
        $boundry = str_replace("\"","" ,$boundry[1]);
        $this->rawFields['boundry'] = $boundry;
      }
      return $this->rawFields['boundry'];
    }

    /**
     * Gets the To field (Receiver) from an Email if it exists
     * @return string (in UTF-8 format)
     * @throws Exception if a To header is not found
     */
    public function getTo() {
        if(!array_key_exists('to', $this->rawFields)) {
            throw new Exception('Could not find To in Email');
        }
        return $this->rawFields['to'];
    }

    /**
     * Gets the From field (Sender) from an Email if it exists
     * @return string (in UTF-8 format)
     * @throws Exception if a From header is not found
     */
    public function getFrom() {
        if(!array_key_exists('from', $this->rawFields)) {
            throw new Exception('Could not find From in EMail');
        }
        return $this->rawFields['from'];
    }

    /**
     * Gets list of email addresses that this email was also sent to. If any 
     * exist
     * @returns string (in UTF-8 format)
     * @throws Exception if a CC header is not found
     */
    public function getCc() {
        if(!array_key_exists('cc', $this->rawFields)) {
            throw new Exception('Could not find CC in Email');
        }
        return $this->rawFields['cc'];
    }

    /**
     * Gets the subject of the Email if one exists
     * @return string (in UTF-8 format)
     * @throws Exception if a Subject heaer is not found
     */
    public function getSubject() {
        if(!array_key_exists('subject', $this->rawFields)) {
            throw new Exception('Could not find Subject in Email');
        }
        return $this->rawFields['subject'];
    }

    /**
     * Retrieves the Date and Time on which the email was sent
     * @return string (in UTF-8 format)
     * @throws Exception if a Date header is not found
     */
    public function getEmailDate() {
        if(!array_key_exists('date', $this->rawFields)) {
            throw new Exception('Could not find Date in Email');
        }
        return $this->rawFields['date']; 
    }

    /**
     * Implodes array of rawBodyLines
     * @return string UTF8 Encoded
     */
    public function getBody() {
        foreach($this->rawBodyLines as $bodyLine) {
            $bodyLine.="\n<br />";
        }
        return $this->rawBodyLines;//implode($this->rawBodyLines);
    }

    public function splitBodyOnBoundry ($boundry, $bodyContent) {
      $boundry = "--".$boundry."--";
      $newBody = array();
      $x = 0;
      foreach($bodyContent as $line) {
        if($line == $boundry) {
          $x++;
          $newBody["bodySection$x"] = array();
        } else {
          array_push($newBody["bodySection$x"], $line);
        }
      }

      // Unset null and length < 3
      foreach($newBody as $key => $arr) {
        if(sizeof($arr) < 3 || $arr == null)
          unset($newBody[$key]);
      }
      
      return $newBody;
    }

    public function getHTMLBody($boundry, $bodyContent) {
      $boundry = $boundry;
      $bodyContent = $bodyContent;
      $parsedBody = self::splitBodyOnBoundry($boundry, $bodyContent);
      foreach($parsedBody as $bodyArray) {
        if(in_array('Content-Type: text/html; charset=ISO-8859-1', $bodyArray)) {
          foreach($bodyArray as $key => $arrElem) {
            $pattern = '/^\b(Content[-\w]+)\b[:]/';
            if(strlen($arrElem) == 0 || preg_match($pattern, $arrElem)) {
              unset($bodyArray[$key]);
            }
          }
          return implode($bodyArray);
        }  
      }
      return "";
    }

    public function getPlainBody($boundry, $bodyContent) {
      $boundry = $boundry;
      $bodyContent = $bodyContent;
      $parsedBody = self::splitBodyOnBoundry($boundry, $bodyContent);
      foreach($parsedBody as $bodyArray) {
        if(in_array('Content-Type: text/plain; charset=ISO-8859-1; Format=Flowed', $bodyArray)) {
          foreach($bodyArray as $key => $arrElem) {
           $pattern = '/^\b(Content[-\w]+)\b[:]/';
            if(strlen($arrElem) == 0 || preg_match($pattern, $arrElem)) {
              unset($bodyArray[$key]);
            }
 
          }
          return implode($bodyArray);
        }  
      }
      return "";
    }

    /**
     *
     * Prints all of the relavant information from the email
     * But only prints information that exists.
     */
    public function printEmail() {
        try {
          print_r("Boundry: ".$this->getBoundry() . "\n<br />");
        } catch(Exception $e) {}
        try {
          print_r("Content-Type: ". $this->getContentType() . "\n<br />");
        } catch(Exception $e) {}
        try {
            print_r("To: " . $this->getTo() . "\n<br />");
        } catch(Exception $e) {}
        try {
            print_r("CC: " . $this->getCc() . "\n<br />");
        } catch(Exception $e) {}
        try {
            print_r("From: " . $this->getFrom() . "\n<br />");
        } catch(Exception $e) {}
        try {
            print_r("Subject: " . $this->getSubject() . "\n<br />");
        } catch(Exception $e) {}
        try {
            print_r("Sent on: " . $this->getEmailDate() . "\n<br />");
        } catch(Exception $e) {}
        try {
          print_r("<br /><br /><br />Body: <br />" . var_dump($this->getBody()) . "<br />");
        } catch(Exception $e) {}
        try {
            print_r("<br /><br /><br />\n\n\n" . var_dump($this->splitBodyOnBoundry($this->getBody())));
        } catch(Exception $e) {}
    }

    /**
     * Checks for a new line
     * @param string $line
     * @return boolean
     */
    public static function isNewLine($line) {
        $line = str_replace("\r", '', $line);
        $line = str_replace("\n", '', $line);

        return (strlen($line) === 0);
    }

    /**
     * Checks that the line starts with a Printable Character
     * @param string $line
     * @return boolean
     */
    private function isLineStartingWithPrintableChar($line)
    {
        return preg_match('/^[A-Za-z]/', $line);
    }

}

/**
 *
 * @todo Parse and format raw body for different MIME types
 */

?>
