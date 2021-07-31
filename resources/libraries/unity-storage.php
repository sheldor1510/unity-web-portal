<?php

class unityStorage
{
    public function __construct()
    {
        
    }

    private static function getTrueNASCurlObject($api_key) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $api_key));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);  // comment for debug

        return $curl;
    }

    public function createHomeDirectory($user)
    {
        // This method should be redone for different deployments

        $storage_device = "nas1";
        $CONF = config::STORAGE[$storage_device];
        $curl = unityStorage::getTrueNASCurlObject($CONF["key"]);

        $dataset = $CONF["home_dataset"] . $user;

        $web_uid = config::WEB_UID;

        // CREATE DATASET
        $home_quota = config::STORAGE["home_quota"];
        $path = $CONF["host"] . "/pool/dataset";
        $data = <<<DATA
        {
            "name": "$dataset",
            "quota": $home_quota
        }
        DATA;

        curl_setopt($curl, CURLOPT_POST, 1);  // this is a post request
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);  // send data

        curl_setopt($curl, CURLOPT_URL, $path);

        curl_exec($curl);
        if (curl_getinfo($curl)["http_code"] != 200) {
            throw new Exception("Unable to create dataset for home directory");
        }

        // SET DATASET PERMS
        $path = $CONF["host"] . "/pool/dataset/id/" . str_replace('/', '%2F', $dataset) . "/permission";
        $data = <<<DATA
        {
            "user": "$user",
            "group": "$user",
            "acl": [
                {
                    "tag": "owner@",
                    "id": null,
                    "type": "ALLOW",
                    "perms": {"BASIC": "FULL_CONTROL"},
                    "flags": {"BASIC": "INHERIT"}
                },
                {
                    "tag": "USER",
                    "id": "$web_uid",
                    "type": "ALLOW",
                    "perms": {"BASIC": "FULL_CONTROL"},
                    "flags": {"BASIC": "INHERIT"}
                }
            ],
            "options": {
                "stripacl": false,
                "recursive": true,
                "traverse": true
            }
        }
        DATA;
        
        curl_setopt($curl, CURLOPT_POST, 1);  // this is a post request
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);  // send data

        curl_setopt($curl, CURLOPT_URL, $path);
        curl_exec($curl);

        if (curl_getinfo($curl)["http_code"] != 200) {
            $this->deleteHomeDirectory($user);
            throw new Exception("Unable to create dataset for home directory");
        }

        curl_close($curl);  // close session

        $this->populateHomeDirectory($user);  // populate initial files in home directory
    }

    public function deleteHomeDirectory($user)
    {
        // This method should be redone for different deployments

        $storage_device = "nas1";
        $CONF = config::STORAGE[$storage_device];
        $curl = unityStorage::getTrueNASCurlObject($CONF["key"]);

        $dataset = $CONF["home_dataset"] . $user;

        $path = $CONF["host"] . "/pool/dataset/id/" . str_replace('/', '%2F', $dataset);
        $data = <<<DATA
        {
            "recursive": true,
            "force": true
        }
        DATA;

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);  // send data

        curl_setopt($curl, CURLOPT_URL, $path);
        curl_exec($curl);

        if (curl_getinfo($curl)["http_code"] != 200) {
            throw new Exception("Unable to delete dataset");
        }

        curl_close($curl);
    }

    public function updateHomeDirectory($user, $size)
    {
        // This method should be redone for different deployments
        throw new Exception("Not yet implemented");
    }

    public function getHomeDirectory($user) {
        throw new Exception("Not yet implemented");
    }

    private function populateHomeDirectory($user) {
        $source_dir = config::DOCS_ROOT . "/etc/skel/home";
        $source_dir_obj = new FilesystemIterator($source_dir);

        $dest_dir = config::STORAGE["home_location"] . "/" . $user;

        foreach ($source_dir_obj as $file) {
            $filename = $file->getFilename();
            $destination = $dest_dir . "/" . $filename;
            copy($source_dir . "/" . $filename, $destination);
            chown($destination, $user);
            chgrp($destination, $user);
        }

        //create scratch space
        $scratch_web_location = config::STORAGE["scratch_web_mount"] . "/" . $user;
        mkdir($scratch_web_location, 0700, true);
        $this->populateScratchDirectory($user);

        // set directory perms after populating it (then the website loses access!) TODO in the future we should use posix ACLs on the scratch space as well so the website has permanent access, but we don't have any features that can take care of that right now
        chgrp($scratch_web_location, $user);
        chown($scratch_web_location, $user);

        // create scratch space sym link
        symlink(config::STORAGE["scratch_mount"] . "/" . $user, $dest_dir . "/scratch");
    }

    private function populateScratchDirectory($user) {
        $source_dir = config::DOCS_ROOT . "/etc/skel/scratch";
        $source_dir_obj = new FilesystemIterator($source_dir);

        $dest_dir = config::STORAGE["scratch_web_mount"] . "/" . $user;

        foreach ($source_dir_obj as $file) {
            $filename = $file->getFilename();
            $destination = $dest_dir . "/" . $filename;
            copy($source_dir . "/" . $filename, $destination);
            chown($destination, $user);
            chgrp($destination, $user);
        }
    }
}
