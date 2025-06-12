<?php

namespace Lambda\Puzzle;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class DBBackup
{
    public function backup()
    {
        if (!config('lambda.backup.enable') == true) {
            Log::info('Backup not enabled');
            return false;
        }

        $path = storage_path('backup');
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $exe = config('lambda.backup.exe_path');

        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupFile = storage_path("backup/backup_{$db}_{$timestamp}.sql");
        Log::info("Creating MySQL dump...");

        $dumpCommand = "{$exe} --single-transaction -h {$host} -u {$user} -p'{$pass}' {$db} > {$backupFile} 2>&1";
        exec($dumpCommand, $output, $return);
        Log::info("Dump command output", ['output' => $output, 'return' => $return]);

        if ($return !== 0) {
            Log::error("Backup failed.");
            return false;
        }

        Log::info("Backup created at {$backupFile}");
        $this->deleteOldBackups(storage_path('backup'), 3);
        return $backupFile;
    }

    function send($backupFile)
    {
        if (!config('lambda.backup.remote') == true) {
            Log::info('Remote not enabled');
            return false;
        }

        // === Upload via SCP ===
        $remoteUser = config('lambda.backup.remote_username');
        $remotePass = config('lambda.backup.remote_password');
        $remoteHost = config('lambda.backup.remote_host');
        $remotePort = config('lambda.backup.remote_port');
        $remotePath = config('lambda.backup.remote_path');
        $result = $this->upload($backupFile, $remoteHost, $remoteUser, $remotePass, $remotePort, $remotePath);
        Log::info($result);
    }

    public function upload($localFile, $remoteHost, $remoteUser, $remotePass, $remotePort, $remotePath)
    {

        $scriptPath = storage_path('app/tmp_scp_script.exp');

        $scriptLines = [
            '#!/usr/bin/expect -f',
            'set timeout -1',
            "spawn scp -P $remotePort \"$localFile\" \"$remoteUser@$remoteHost:$remotePath\"",
            'expect {',
            '    "*yes/no*" {',
            '        send "yes\r"',
            '        exp_continue',
            '    }',
            '    "*assword:*" {',
            '        send -- {' . $remotePass . '}',
            '        send "\r"',
            '    }',
            '}',
            'expect eof'
        ];

        file_put_contents($scriptPath, implode(PHP_EOL, $scriptLines));
        chmod($scriptPath, 0700);

        exec("expect $scriptPath 2>&1", $output, $returnCode);

        unlink($scriptPath); // delete script

        return [
            'success' => $returnCode === 0,
            'output' => $output,
            'code' => $returnCode
        ];
    }

    function deleteOldBackups($path = 'storage/backup', $days = 3)
    {
        $files = File::files($path);
        $now = now();

        foreach ($files as $file) {
            if ($now->diffInDays(\Carbon\Carbon::createFromTimestamp(File::lastModified($file))) > $days) {
                File::delete($file);
            }
        }
    }
}