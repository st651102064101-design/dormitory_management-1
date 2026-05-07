# Auto-Commit Image Uploads Feature

## Overview
This feature automatically commits uploaded files to git whenever images are uploaded through the dormitory management system. This ensures all uploaded files are tracked in version control without manual intervention.

## Implementation Details

### New File: GitHelper.php
Located at: `/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/GitHelper.php`

This utility class provides:
- `autoCommitFile($filePath, $message)` - Commits a single file
- `autoCommitMultipleFiles($filePaths, $message)` - Commits multiple files
- `isGitAvailable()` - Checks if git is available and repo exists

### Modified Upload Handlers
The following files have been updated to automatically commit uploads:

#### 1. **Payment Proof Uploads**
- `Manage/process_payment.php` - Admin payment processing
- `Tenant/payment.php` - Tenant payment submissions
- `Manage/process_deposit_refund.php` - Refund proof uploads

Files uploaded to: `Public/Assets/Images/Payments/`
Commit message: `Add payment proof: <filename>`

#### 2. **Room Images**
- `Manage/upload_room_image.php` - Direct room image upload
- `Manage/add_room.php` - New room creation with image
- `Manage/process_room.php` - Room creation via form
- `Manage/update_room.php` - Room update with new image

Files uploaded to: `Public/Assets/Images/Rooms/`
Commit message: `Add room image: <filename>`

#### 3. **Repair Images**
- `Tenant/repair.php` - Tenant repair request with image

Files uploaded to: `Public/Assets/Images/Repairs/`
Commit message: `Add repair image: <filename>`

#### 4. **System Settings Images**
- `Manage/save_system_settings.php` - Logo, background, QR code, signature uploads

Files uploaded to: `Public/Assets/Images/`
Commit messages:
- Logo: `Add logo: <filename>`
- Background: `Add background: <filename>`
- LINE QR: `Add LINE QR code: <filename>`
- Signature: `Add owner signature: <filename>`

## How It Works

1. **Upload Process**: When a user uploads an image through any form in the system
2. **File Storage**: The file is stored in the appropriate directory
3. **Database Update**: File information is saved to the database
4. **Auto-Commit**: GitHelper automatically:
   - Checks if git is available
   - Verifies the directory is a git repository
   - Stages the file with `git add`
   - Creates a commit with an appropriate message
   - Initiates a background `git push` (non-blocking)

## Features

✅ **Non-blocking**: Push operations run in the background
✅ **Error Handling**: Logs failures without disrupting uploads
✅ **Automatic Messages**: Generates appropriate commit messages based on upload type
✅ **Safe**: Only commits if git is available and repository exists
✅ **Configurable**: Can override commit messages when needed

## Error Handling

The implementation is designed to never break the upload process:
- If git is not installed, upload succeeds but commit is skipped
- If not a git repository, upload succeeds but commit is skipped
- Git errors are logged but don't affect file uploads
- Users receive success confirmation regardless of git status

## Usage Example

```php
require_once __DIR__ . '/../GitHelper.php';

// After file upload
if (move_uploaded_file($tmp_path, $target_path)) {
    $relative_path = 'Public/Assets/Images/Payments/file.jpg';
    GitHelper::autoCommitFile($relative_path);
}
```

## Commit Log Example

```
4fd6b96 Add auto-commit functionality for image uploads - commits uploaded files to git automatically
74d1ce2 Add auto-commit functionality for uploaded payment and repair proof images
```

## Benefits

1. **Version Control**: All uploaded files are tracked in git
2. **Audit Trail**: Complete history of what was uploaded and when
3. **Rollback Capability**: Can revert to previous file versions if needed
4. **Compliance**: Automatic documentation of document uploads
5. **Backup**: Files are automatically included in git backups

## Configuration

The feature requires:
- Git installed on the server
- The project directory to be a git repository
- Write permissions for the git user

## Future Enhancements

Potential improvements:
- Scheduled batch commits for performance
- Notification system for failed commits
- Configurable commit message templates
- Selective auto-commit (whitelist/blacklist file types)
- Integration with CI/CD pipelines

## Testing

To verify the feature is working:

1. Upload an image through any form (room, payment, repair, etc.)
2. Check git log: `git log --oneline | head -5`
3. Verify commit message appears with the uploaded file
4. Check file exists in git: `git ls-files | grep "Public/Assets/Images"`

## Troubleshooting

**Uploads work but git commits don't appear:**
- Verify git is installed: `which git`
- Check if directory is a git repo: `git status`
- Check error logs for git-related errors
- Verify git user has write permissions

**Performance issues:**
- Monitor background push operations
- Consider using CI/CD for pushing to remote
- Adjust git push timeout if needed

---

**Last Updated:** May 7, 2025
**Feature Status:** ✅ Active
