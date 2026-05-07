<?php
/**
 * GitHelper - Auto-commit uploaded files to git
 * ตัวช่วยสำหรับ auto-commit ไฟล์ที่อัปโหลดขึ้น git
 */

class GitHelper
{
    private static $gitDir = null;
    private static $commitTimeout = 5; // seconds

    /**
     * Initialize git directory
     */
    private static function initGitDir()
    {
        if (self::$gitDir === null) {
            self::$gitDir = __DIR__;
        }
    }

    /**
     * Auto-commit an uploaded file to git and push
     * @param string $relativeFilePath - Relative path from git root (e.g., 'Public/Assets/Images/Payments/file.jpg')
     * @param string $commitMessage - Custom commit message (optional)
     * @return array ['success' => bool, 'message' => string, 'output' => string]
     */
    public static function autoCommitFile($relativeFilePath, $commitMessage = '')
    {
        self::initGitDir();

        if (empty($relativeFilePath)) {
            return ['success' => false, 'message' => 'File path is empty'];
        }

        // Check if git is available
        exec('which git', $gitCheck, $gitCheckCode);
        if ($gitCheckCode !== 0) {
            return ['success' => false, 'message' => 'Git is not installed or not found'];
        }

        // Check if directory is a git repository
        if (!is_dir(self::$gitDir . '/.git')) {
            return ['success' => false, 'message' => 'Current directory is not a git repository'];
        }

        // Determine file type for commit message
        if (empty($commitMessage)) {
            $ext = strtolower(pathinfo($relativeFilePath, PATHINFO_EXTENSION));
            $fileType = match ($ext) {
                'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image',
                'pdf' => 'document',
                default => 'file'
            };

            // Generate descriptive commit message based on file type
            if (strpos($relativeFilePath, 'Payments') !== false) {
                $commitMessage = "Add payment proof: {$relativeFilePath}";
            } elseif (strpos($relativeFilePath, 'Rooms') !== false) {
                $commitMessage = "Add room image: {$relativeFilePath}";
            } elseif (strpos($relativeFilePath, 'Repairs') !== false) {
                $commitMessage = "Add repair image: {$relativeFilePath}";
            } else {
                $commitMessage = "Add {$fileType}: {$relativeFilePath}";
            }
        }

        try {
            // Build commands
            $addCmd = "cd " . escapeshellarg(self::$gitDir) . 
                      " && git add " . escapeshellarg($relativeFilePath);
            
            $commitCmd = "cd " . escapeshellarg(self::$gitDir) . 
                         " && git commit -m " . escapeshellarg($commitMessage);
            
            $pushCmd = "cd " . escapeshellarg(self::$gitDir) . 
                       " && git push 2>&1";

            $output = [];
            $exitCode = 0;

            // Execute git add
            exec($addCmd, $output, $exitCode);
            if ($exitCode !== 0) {
                $addError = implode("\n", $output);
                error_log("Git add failed for {$relativeFilePath}: {$addError}");
                return ['success' => false, 'message' => 'Git add failed', 'output' => $addError];
            }

            // Execute git commit
            $commitOutput = [];
            exec($commitCmd, $commitOutput, $commitExitCode);
            
            if ($commitExitCode !== 0) {
                // Commit might fail if nothing to commit, that's okay
                $commitError = implode("\n", $commitOutput);
                error_log("Git commit result for {$relativeFilePath}: {$commitError}");
            }

            // Execute git push in background (don't wait for it)
            exec($pushCmd . " > /dev/null 2>&1 &");

            return [
                'success' => true,
                'message' => 'File committed and push initiated',
                'output' => implode("\n", $commitOutput)
            ];

        } catch (Exception $e) {
            error_log("GitHelper error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Auto-commit multiple files
     * @param array $filePaths - Array of relative file paths
     * @param string $commitMessage - Custom commit message
     * @return array ['success' => bool, 'message' => string, 'results' => array]
     */
    public static function autoCommitMultipleFiles($filePaths, $commitMessage = 'Add uploaded files')
    {
        if (empty($filePaths)) {
            return ['success' => false, 'message' => 'No files provided'];
        }

        self::initGitDir();

        try {
            // Check if git is available
            exec('which git', $gitCheck, $gitCheckCode);
            if ($gitCheckCode !== 0) {
                return ['success' => false, 'message' => 'Git is not installed'];
            }

            if (!is_dir(self::$gitDir . '/.git')) {
                return ['success' => false, 'message' => 'Not a git repository'];
            }

            $output = [];

            // Build multi-file add command
            $addCmd = "cd " . escapeshellarg(self::$gitDir);
            foreach ($filePaths as $filePath) {
                $addCmd .= " && git add " . escapeshellarg($filePath);
            }

            exec($addCmd, $addOutput, $addExitCode);
            if ($addExitCode !== 0) {
                return ['success' => false, 'message' => 'Git add failed'];
            }

            // Commit
            $commitCmd = "cd " . escapeshellarg(self::$gitDir) . 
                         " && git commit -m " . escapeshellarg($commitMessage);
            exec($commitCmd, $commitOutput, $commitExitCode);

            // Push in background
            exec("cd " . escapeshellarg(self::$gitDir) . " && git push > /dev/null 2>&1 &");

            return [
                'success' => true,
                'message' => 'Multiple files committed and push initiated',
                'results' => ['files_added' => count($filePaths)]
            ];

        } catch (Exception $e) {
            error_log("GitHelper multi-commit error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if git is available and repository exists
     * @return bool
     */
    public static function isGitAvailable()
    {
        self::initGitDir();
        exec('which git', $output, $code);
        return $code === 0 && is_dir(self::$gitDir . '/.git');
    }
}
?>
