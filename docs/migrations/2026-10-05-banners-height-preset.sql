-- Adds a "Ukuran banner (desktop)" preset to the banners table.
-- Values: '400' | '500' (default) | '700' — mapped to CSS height presets
-- in includes/site-header.php ($bnPresetMap).
ALTER TABLE banners
  ADD COLUMN height_preset VARCHAR(10) NOT NULL DEFAULT '500' AFTER placement;
