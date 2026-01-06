#!/bin/bash
# Quick commit and auto-push script
# Usage: ./quick-commit.sh "commit message"

cd /Applications/XAMPP/xamppfiles/htdocs/dormitory_management

# Check if message provided
if [ -z "$1" ]; then
    echo "❌ กรุณาระบุข้อความ commit"
    echo "ตัวอย่าง: ./quick-commit.sh \"แก้ไข booking system\""
    exit 1
fi

echo "📝 กำลัง commit และ push..."

# Add all changes
git add .

# Commit with message
git commit -m "$1"

# Push will happen automatically via post-commit hook

echo ""
echo "✅ เสร็จสิ้น!"
echo "📌 Commit: $1"
