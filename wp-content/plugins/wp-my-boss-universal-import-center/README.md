# 🎬 UNI-IM-PORT — Universal Import Center

**Version:** 1.0.15.3  
**Requires WordPress:** 5.8 or higher  
**Requires PHP:** 7.4 or higher  
**License:** GPLv2 or later

## 📖 Description

UNI-IM-PORT is a professional bulk import system for WordPress that respects existing data and prevents duplication. It's designed as a "Courier" — delivering data without taking ownership or overriding website settings.

## 🎯 Core Philosophy

1. **ผู้ส่งข้อมูล (Courier), not Controller** — The plugin imports data; the site owner controls the website
2. **Highest Authority to Existing Data** — Never overwrite or delete what's already there without explicit user action
3. **Prevent Duplicates** — Normalize names, check for existing terms/posts/media before creating
4. **Respect Site Configuration** — No touching Robots, Index, SEO settings, or Config — that's the site owner's domain

## ✨ Features

### 📥 Multi-Format Import
- **CSV/TSV** — Spreadsheet-style bulk import
- **JSON** — Structured data import with full payload support
- **ZIP** — Batch processing for complex structures
- **Excel** — XLSX support (coming soon)

### 🔍 Smart Duplicate Detection
- Normalize taxonomy names (trim, lowercase, remove diacritics)
- Case-insensitive matching
- Slug-based verification
- Post count weighting for selection

### 📝 Meta Key Management
**7 Meta Keys Written:**
- `_yoast_wpseo_focuskw` (Yoast Focus Keyphrase)
- `_yoast_wpseo_metadesc` (Yoast Meta Description)
- `duration` (Video length in seconds)
- `video_url` (External video link)
- `wpmb_gallery_ids` (Gallery media IDs)
- `_thumbnail_id` (Featured Image ID)
- `_wpmb_code` (Internal tracking code)

**14 Meta Keys Permanently Blocked:**
- Rank Math fields (5 keys)
- Legacy poster fields (4 keys)
- Deprecated fields (5 keys)

### 🛡️ Security & Audit
- Complete activity logging
- Field-level import rules (overwrite, fill-empty, preserve)
- Per-field enable/disable control
- Import decision audit trail

### 🧹 Site Maintenance
- **Taxonomy Deduplication** — Merge whitespace-only duplicates
- **Media Scanner** — Find unused images
- **Unused Cleanup** — Safe trash of truly unused media
- **Post Index** — Quick lookup and reference

## 🚀 Quick Start

### Installation
1. Upload to `/wp-content/plugins/wp-my-boss-universal-import-center/`
2. Activate from WordPress admin
3. Navigate to **UNI-IM-PORT** menu

### First Import
1. Create a new Import Project
2. Upload CSV/JSON file
3. Map fields if needed
4. Validate items
5. Start import queue

### CSV Format
```
code,title,category,post_tag
ABC123,My Post,Movies,Action
DEF456,Another Post,Series,Drama
```

### JSON Format
```json
{
  "ABC123": {
    "title": "My Post",
    "content": "Post content here",
    "taxonomies": {
      "category": ["Movies"],
      "post_tag": ["Action"]
    },
    "meta": {
      "duration": "120"
    }
  }
}
```

## 📚 Documentation

- **Policy Document** — See `PLUGIN_POLICY_TH.md` for official guidelines
- **Patch Notes** — See `PATCH_NOTES_v1.0.15.3_TH.txt` for release details

## 🔧 Developer API

### Programmatic Import
```php
$project_id = WPMB_UIC_DB::instance()->create_project('My Project');
WMPB_UIC_DB::instance()->upsert_item($project_id, 'ABC123', array(
    'title' => 'My Post',
    'content' => 'Content',
    'taxonomies' => [
        'category' => ['Movies']
    ]
));
```

### Export Package
```php
$package = WPMB_UIC_Exporter::export_matched_package(
    array(123, 456, 789)
);
echo wp_json_encode($package);
```

## ⚖️ License & Credits

GPLv2 or later — Free to use, modify, and distribute  
© 2026 WP MY BOSS / UNI-IM-PORT Team

## 🤝 Support

For issues, please create an issue on the GitHub repository.
