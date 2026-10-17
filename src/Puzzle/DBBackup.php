<?php

namespace Lambda\Puzzle;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

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

        // Values are passed through the environment so they are never interpolated into the shell command,
        // the password is not visible in `ps`, and stderr is kept out of the dump file.
        $process = Process::fromShellCommandline(
            '"$DUMP_EXE" --single-transaction -h "$DUMP_HOST" -u "$DUMP_USER" "$DUMP_DB" > "$DUMP_FILE"',
            null,
            [
                'DUMP_EXE' => $exe ?: 'mysqldump',
                'DUMP_HOST' => $host,
                'DUMP_USER' => $user,
                'DUMP_DB' => $db,
                'DUMP_FILE' => $backupFile,
                'MYSQL_PWD' => (string)$pass,
            ]
        );
        $process->setTimeout(null);
        $return = $process->run();
        Log::info("Dump command output", ['output' => $process->getErrorOutput(), 'return' => $return]);

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
            'spawn scp -P $env(SCP_PORT) $env(SCP_FILE) $env(SCP_TARGET)',
            'expect {',
            '    "*yes/no*" {',
            '        send "yes\r"',
            '        exp_continue',
            '    }',
            '    "*assword:*" {',
            '        send -- "$env(SCP_PASS)\r"',
            '    }',
            '}',
            'expect eof'
        ];

        // The script holds no secrets; values come from the environment so they can't break out of Tcl
        file_put_contents($scriptPath, implode(PHP_EOL, $scriptLines));
        chmod($scriptPath, 0700);

        try {
            $process = new Process(['expect', $scriptPath], null, [
                'SCP_PORT' => (string)$remotePort,
                'SCP_FILE' => $localFile,
                'SCP_TARGET' => "$remoteUser@$remoteHost:$remotePath",
                'SCP_PASS' => (string)$remotePass,
            ]);
            $process->setTimeout(null);
            $returnCode = $process->run();
        } finally {
            @unlink($scriptPath); // delete script
        }

        return [
            'success' => $returnCode === 0,
            'output' => $process->getOutput() . $process->getErrorOutput(),
            'code' => $returnCode
        ];
    }

    function deleteOldBackups($path = 'storage/backup', $days = 3)
    {
        $files = File::files($path);
        $threshold = now()->subDays($days)->getTimestamp();

        foreach ($files as $file) {
            if (File::lastModified($file) < $threshold) {
                File::delete($file);
            }
        }
    }
}