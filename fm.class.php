<?php class FM {
    // Method to copy a file
    public function copyFile($source, $destination) {
        return copy($source, $destination);
    }

    // Method to rename a file or folder
    public function rename($oldName, $newName) {
        return rename($oldName, $newName);
    }

    // Method to delete a file or folder
    public function delete($path) {
        if (is_dir($path)) {
            return rmdir($path);
        } else {
            return unlink($path);
        }
    }

    // Method to move a file or folder
    public function move($source, $destination) {
        return rename($source, $destination);
    }

    // Method to backup a file or folder
    public function backup($source, $backupDir) {
        return copy($source, $backupDir . '/' . basename($source));
    }

    // Method to archive a folder (compress it)
    public function archive($folder, $archiveName) {
        $zip = new ZipArchive();
        if ($zip->open($archiveName, ZipArchive::CREATE) === TRUE) {
            $this->addFolderToZip($folder, $zip);
            $zip->close();
            return true;
        } else {
            return false;
        }
    }

    private function addFolderToZip($folder, $zipFile, $subFolder = '') {
        $files = scandir($folder);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($folder . '/' . $file)) {
                    $this->addFolderToZip($folder . '/' . $file, $zipFile, $subFolder . $file . '/');
                } else {
                    $zipFile->addFile($folder . '/' . $file, $subFolder . $file);
                }
            }
        }
    }

    // Method to upload a file
    public function upload($file, $destination) {
        return move_uploaded_file($file['tmp_name'], $destination);
    }

    // Method to download a file
    public function download($filePath) {
        if (file_exists($filePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    }
}
?>