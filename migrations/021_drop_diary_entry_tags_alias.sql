-- Consolidate diary entry-tag links into a single table.
-- Migration 015 created diary_entry_tags as an "alias" of diary_tag_links,
-- but all writes go to diary_tag_links, so reads against the alias always
-- returned nothing. All code now uses diary_tag_links exclusively; copy any
-- stray rows across and drop the alias table.

INSERT IGNORE INTO diary_tag_links (entry_id, tag_id, created_at)
SELECT det.entry_id, det.tag_id, det.created_at
FROM diary_entry_tags det;

DROP TABLE IF EXISTS diary_entry_tags;
