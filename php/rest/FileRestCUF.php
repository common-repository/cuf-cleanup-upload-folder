<?php

/**
 * Description of Backup
 *
 * @author nicearma
 */
class FileRestCUF extends BasicRestCUF
{

    function __construct()
    {
        parent::__construct();
    }

    public function getAllDirectories()
    {

        $dirBase = $this->help->uploadDir();
        $recursiveDir = new RecursiveDirectoryIterator($dirBase, RecursiveDirectoryIterator::SKIP_DOTS);

        $iter = new RecursiveIteratorIterator(
            $recursiveDir, RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        $paths = array('base' => $dirBase);

        foreach ($iter as $path => $dir) {
            if ($dir->isDir()) {
                $paths["dirs"][] = str_replace($dirBase, "", $path);
            }
        }


        $this->help->generateResponseOk($paths);
    }

    public function getAllDirectoriesFromDirectory()
    {
        // $json=$this->help->getObjectFromJson();
        $dirBase = $this->help->uploadDir();
        $dirIterator = new DirectoryIterator($dirBase);

        $iter = new IteratorIterator($dirIterator);

        $dirs = array();

        foreach ($iter as $file) {
            if (!$file->isFile() && !$file->isDot())
                $dirs[] = $file->getFilename();
        }

        $this->help->generateResponseOk($dirs);
    }

    public function getFilesFromDirectory()
    {

        $jPath = $this->help->getObjectFromJson();

        if (stripos($jPath['path'], "..") !== false) {
            $this->help->generateResponseBad('wrong path');
        }

        $dirPath = $this->help->uploadDir() . $jPath['path'];

        if(!file_exists($dirPath)){
            $this->help->generateResponseBad('wrong path');
        }

        if (!empty($dirPath)) {

            $dirIterator = new DirectoryIterator($dirPath);

            $iter = new IteratorIterator($dirIterator);

            $files = array();

            foreach ($iter as $file) {

                if ($file->isFile()) {
                    $fileCUF = new FileCUF();
                    $fileCUF->setName($file->getFilename());
                    $fileCUF->setPath($jPath['path']);
                    $fileCUF->setSrc($file->getPathname());
                    $fileCUF->setType(mime_content_type($file->getPathname()));
                    $fileCUF->setSize($file->getSize());
                    $fileCUF->status = new StatusCUF();
                    $fileCUF->status->inServer = StatusInServerCUF::$INSERVER;
                    $files[] = $fileCUF;
                }
            }

            $this->help->generateResponseOk($files);
        } else {
            //TODO: empty path
        }
    }


    public function verifyFiles()
    {
        $jSrc = $this->help->getObjectFromJson();
        $jSrc['path']=str_replace("\\", "/", $jSrc['path']);

        if(is_array($jSrc['names'])){
            $result=array();
            foreach($jSrc['names'] as $name){
                $filePath = $this->getFilePath($jSrc['path'],$name);
                $result[$name] = $this->verify($jSrc['path'],$name,$filePath);
            }
        }
        $this->help->generateResponseOk($result);

    }



    public function verifyFile()
    {
        $jSrc = $this->help->getObjectFromJson();
        $jSrc['path']=str_replace("\\", "/", $jSrc['path']);
        $filePath = $this->getFilePath($jSrc['path'],$jSrc['name']);


        $this->help->generateResponseOk( $this->verify($jSrc['path'],$jSrc['name'],$filePath));
    }

    public function deleteFile()
    {
        $jSrc = $this->help->getObjectFromJson();
        $jSrc['path']=str_replace("\\", "/", $jSrc['path']);
        $filePath = $this->help->uploadDir(). $this->getFilePath($jSrc['path'],$jSrc['name']);


        if(file_exists($filePath)){
            @unlink($filePath);
        }
        clearstatcache();

        if(file_exists($filePath)){
            $this->help->generateResponseBad('');
        }else{
            $this->help->generateResponseOk('');
        }
    }


    private function getFilePath($path,$name){

        $filePath = $path . '/' . $name;

        if (stripos($filePath, "..") !== false) {
            $this->help->generateResponseBad('wrong path ..');
        }

        //security
        if (!file_exists($this->help->uploadDir() . $filePath)) {
            $this->help->generateResponseBad('wrong path ..');
        }

        if(!file_exists($this->help->uploadDir().$filePath)){
            $this->help->generateResponseBad("file doesn't exist");
        }

        return $filePath;
    }


    private function verify($path,$name,$filePath){

        $statusResponse = new StatusResponseCUF();
        $status = new statusCUF();
        $status->setAttach(StatusAttachCUF::$UNATTACH);
        $status->setInServer(StatusInServerCUF::$INSERVER);
        $statusResponse->setStatus($status);

        $checkers = new CheckersCUF($this->database);
        $checkerAttach = new CheckerSpecialImageAttachCUF($this->database);
        $checkerAttachPostMeta = new CheckerSpecialImageAttachPostMetaCUF($this->database);


        if ($checkers->verify($name, $this->options)) {
            $status->setUsed(StatusUsedCUF::$USED);
        } else {
            $status->setUsed(StatusUsedCUF::$UNUSED);
        }

        $resultCheckerAttach = $checkerAttach->verify($filePath, $this->options);

        if (!empty($resultCheckerAttach)) {
            $count = count($resultCheckerAttach);

            $status->setAttach(StatusAttachCUF::$ATTACH_ORIGINAL);

            if ($count == 1) {
                $statusResponse->setId($resultCheckerAttach['0']['id']);
            }
        } else {

            $resultCheckerAttachPostMeta = $checkerAttachPostMeta->verify($name, $this->options);

            if (!empty($resultCheckerAttachPostMeta)) {
                $count = count($resultCheckerAttachPostMeta);

                if ($count == 1) {
                    if (stripos($resultCheckerAttachPostMeta['0']['meta_value'], ltrim($path, "/")) !== false) {
                        $statusResponse->setId($resultCheckerAttachPostMeta['0']['post_id']);
                        $status->setAttach(StatusAttachCUF::$ATTACH_META);

                    }
                } else if ($count > 1) {
                    foreach ($resultCheckerAttachPostMeta as $postMeta) {
                        if (stripos($postMeta['meta_value'], ltrim($path, "/")) !== false) {
                            $statusResponse->setId($postMeta['post_id']);
                            $status->setAttach(StatusAttachCUF::$ATTACH_META);
                            break;
                        }

                    }
                }
            }
        }


        return $statusResponse;
    }

    public function readShortCodes()
    {

        $resultContent = ConvertWordpressToCUF::convertIdToHTMLShortCodes($this->database->getShortCodeContent($this->options));
        $resultExcerpt = array();

        if($this->options->isExcerptCheck()){
            $resultExcerpt = ConvertWordpressToCUF::convertIdToHTMLShortCodes($this->database->getShortCodeExcerpt($this->options),'excerpt');
        }

        $result= array_values(array_merge($resultContent,$resultExcerpt));
        $this->help->generateResponseOk($result);

    }

}
