# Repair Image Upload Feature

## Setup Instructions

### 1. Add repair_image column to database

Run the following SQL command in your MySQL database:

```sql
ALTER TABLE repair ADD COLUMN repair_image VARCHAR(255) DEFAULT NULL AFTER repair_desc;
```

Or import the SQL file:
```bash
mysql -u [username] -p [database_name] < add_repair_image_column.sql
```

### 2. Features

- **Upload repair images**: When creating a repair ticket, you can now attach an image
- **Image display**: The repair list table shows a thumbnail (60x60px) for each repair with an image
- **File validation**:
  - Supported formats: JPG, PNG, WebP
  - Maximum file size: 5MB
  - Real-time preview during upload
- **Image storage**: Images are stored in `Assets/Images/Repairs/` directory

### 3. Usage

1. Go to "เพิ่มการแจ้งซ่อม" (Add Repair) form
2. Fill in the required fields (สัญญา, รายละเอียด)
3. Optionally select a repair image (จำเป็น)
4. Click "บันทึกการแจ้งซ่อม" to save

The image will be displayed as a thumbnail in the repair list table under the "รูปภาพ" column.

### 4. Image Management

- Images are automatically named with timestamp and random ID to avoid conflicts
- Original filenames are not preserved for security reasons
- Images stored in: `/Assets/Images/Repairs/`

### 5. Notes

- If no image is provided, a placeholder icon is shown
- Images are optional - the repair can be saved without an image
- The form includes client-side validation with preview
