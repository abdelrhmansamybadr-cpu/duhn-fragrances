-- ================================================================
-- DUHN FRAGRANCES — Seed: Collections + All 36 Products
-- Run this ONCE in phpMyAdmin on u665632021_duhn_db
-- ================================================================

SET NAMES utf8mb4;

-- ── Collections ───────────────────────────────────────────────────
INSERT INTO `collections` (`slug`, `name`, `description`, `sort_order`) VALUES
('bestsellers',       'Bestsellers',           'Our top-selling fragrances loved by thousands',  1),
('new-drops',         'NEW DROPS',             'Recently launched — fresh off the bottle',        2),
('for-him',           'For Him',               'Bold, confident masculine fragrances',            3),
('for-her',           'For Her',               'Elegant, feminine, irresistible scents',          4),
('date-night',        'Date Night',            'Make an unforgettable impression',                5),
('office-fresh',      'Office Fresh',          'Professional, clean, and confident',              6),
('summer-collection', 'Summer Collection',     'Light, fresh, and perfect for warm days',        7),
('universal',         'Universal MUST-HAVEs',  'Versatile everyday essentials',                  8),
('starboy',           'Starboy Collection',    'Bold, distinctive, iconic scents',               9)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ── Products ──────────────────────────────────────────────────────
INSERT INTO `products`
  (`slug`, `name`, `inspired_by`, `description`, `size_ml`, `price`, `currency`, `stock_qty`, `is_featured`, `is_new_drop`, `avg_rating`, `review_count`)
VALUES
('euphoria',        'Euphoria',        'Imagination – Louis Vuitton',    'A euphoric blend of warmth and mystery. Rich, dreamy, and deeply captivating.',                    50, 899.00, 'EGP', 100, 1, 0, 4.80, 24),
('carnation',       'Carnation',       'Aventus – Creed',                'Powerful and iconic. Smoky birch, fresh pineapple, and a clean dry-down.',                         50, 899.00, 'EGP', 100, 1, 0, 4.90, 41),
('blue-essence',    'Blue Essence',    'Dylan Blue – Versace',           'A deep aquatic freshness with violet leaves, grapefruit, and papyrus.',                            50, 899.00, 'EGP', 100, 1, 0, 4.70, 18),
('chillwave',       'Chillwave',       'Pacific Chill – Louis Vuitton',  'Sun-soaked and effortlessly cool. A breezy escape in every spray.',                               50, 899.00, 'EGP', 100, 1, 1, 4.85, 12),
('tropica-xtreme',  'Tropica Xtreme', NULL,                             'An explosion of tropical fruits and exotic woods — bold and addictive.',                           50, 899.00, 'EGP', 100, 1, 1, 4.60, 9),
('citranova',       'Citranova',       'Y Eau de Parfum – YSL',         'Fresh ginger, apple, and a powerful sage heart. Energetic and sharp.',                            50, 899.00, 'EGP', 100, 1, 0, 4.75, 22),
('sweet-chestnut',  'Sweet Chestnut',  NULL,                             'A warm gourmand treat — roasted chestnut wrapped in sweet vanilla and amber.',                    50, 899.00, 'EGP', 100, 1, 0, 4.65, 14),
('obsidian',        'Obsidian',        NULL,                             'Dark, bold, and smoky. A sophisticated oud-leather blend that commands attention.',               50, 899.00, 'EGP', 100, 1, 0, 4.80, 31),
('playboy',         'Playboy',         NULL,                             'Fun, casual, and effortlessly charming. A fresh citrus and musk combo.',                          50, 899.00, 'EGP', 100, 0, 1, 4.50, 7),
('fruity-jungle',   'Fruity Jungle',   NULL,                             'A vibrant explosion of tropical fruits with a green, earthy finish.',                             50, 899.00, 'EGP', 100, 0, 1, 4.55, 8),
('sunset-swim',     'Sunset Swim',     NULL,                             'Warm aquatic notes of sea salt, coconut water, and sandalwood.',                                  50, 899.00, 'EGP', 100, 0, 0, 4.60, 11),
('seaside-colada',  'Seaside Colada',  NULL,                             'Tropical sweetness meets ocean breeze — pineapple, coconut, and sea mist.',                      50, 899.00, 'EGP', 100, 0, 0, 4.50, 6),
('saffron-rum',     'Saffron Rum',     NULL,                             'An oriental masterpiece — spiced saffron, rum, and warm oud base.',                              50, 899.00, 'EGP', 100, 0, 0, 4.70, 16),
('warrior',         'Warrior',         NULL,                             'Bold, fierce, and powerful. A commanding masculine blend of spice and wood.',                    50, 899.00, 'EGP', 100, 0, 0, 4.75, 19),
('gourmand-cafe',   'Gourmand Cafe',   NULL,                             'Rich coffee, roasted vanilla, and dark cocoa. Sweet and deeply comforting.',                     50, 899.00, 'EGP', 100, 0, 0, 4.65, 13),
('fruity-musk',     'Fruity Musk',     NULL,                             'Soft, feminine, and inviting. Ripe peach, raspberry, and a clean musk dry-down.',               50, 899.00, 'EGP', 100, 0, 0, 4.55, 10),
('baked-vanilla',   'Baked Vanilla',   'Vanilla 28 – Kayali',           'Pure indulgence. Rich baked vanilla, caramelised sugar, and soft musk.',                         50, 899.00, 'EGP', 100, 0, 0, 4.85, 28),
('cookies-and-cream','Cookies & Cream','Althaïr – Parfums de Marly',    'A sophisticated gourmand — almond, vanilla cream, and warm sandalwood.',                         50, 899.00, 'EGP', 100, 0, 0, 4.80, 21),
('desire',          'Desire',          'Libre – YSL',                   'Lavender, mandarin, and Madagascar vanilla in a bold floral rebel.',                              50, 899.00, 'EGP', 100, 0, 0, 4.75, 17),
('devils-lychee',   "Devil's Lychee",  'Delina – Parfums de Marly',     'Lychee, rose, and musks in an irresistible, feminine pink cloud.',                               50, 899.00, 'EGP', 100, 0, 1, 4.80, 23),
('espionage',       'Espionage',       'Naxos – Xerjoff',               'Lavender, tobacco, and honey — an intoxicating and mysterious blend.',                           50, 899.00, 'EGP', 100, 0, 0, 4.85, 26),
('ethereal-cognac', 'Ethereal Cognac', "Angel's Share – Kilian",        'Cognac, cinnamon, tonka bean, and sandalwood. A luxurious, warming sip.',                        50, 899.00, 'EGP', 100, 0, 0, 4.90, 33),
('cinnamon-honey',  'Cinnamon Honey',  'Oajan – Parfums de Marly',      'Warm cinnamon, honey, and vanilla musk — sweet, spiced, and seductive.',                        50, 899.00, 'EGP', 100, 0, 0, 4.75, 15),
('cloud9',          'Cloud9',          'Her – Burberry',                 'Soft pink pepper, jasmine, and a clean white musk. Feminine and free.',                          50, 899.00, 'EGP', 100, 0, 0, 4.70, 20),
('feral-rose',      'Feral Rose',      NULL,                             'A wild, dark rose with oud, patchouli, and smoky woods.',                                        50, 899.00, 'EGP', 100, 0, 0, 4.60, 9),
('golden-essence',  'Golden Essence',  NULL,                             'Warm amber, golden oud, and saffron wrapped in a rich, velvety dry-down.',                      50, 899.00, 'EGP', 100, 0, 0, 4.70, 12),
('hetrodox',        'Hetrodox',        NULL,                             'Unconventional and avant-garde. A bold clash of contrasts that defies expectation.',             50, 899.00, 'EGP', 100, 0, 0, 4.65, 8),
('midnight-oman',   'Midnight Oman',   NULL,                             'Deep oud, frankincense, and rose — an authentic oriental treasure.',                             50, 899.00, 'EGP', 100, 0, 0, 4.85, 29),
('minerva',         'Minerva',         NULL,                             'Crisp, clean, and intellectual. Bergamot, white tea, and cedar.',                               50, 899.00, 'EGP', 100, 0, 0, 4.55, 7),
('nude-bloom',      'Nude Bloom',      NULL,                             'Soft magnolia, peony, and skin musk in a barely-there floral cloud.',                           50, 899.00, 'EGP', 100, 0, 0, 4.60, 11),
('sinful-rose',     'Sinful Rose',     NULL,                             'A dark, thorny rose with black pepper, patchouli, and smoky vetiver.',                          50, 899.00, 'EGP', 100, 0, 0, 4.70, 14),
('unholy',          'Unholy',          NULL,                             'Intense, provocative, and unapologetic. Dark spice, black oud, and smoke.',                     50, 899.00, 'EGP', 100, 0, 0, 4.75, 18),
('val',             'Val',             NULL,                             'Versatile and timeless. A balanced blend for any occasion, any season.',                        50, 899.00, 'EGP', 100, 0, 0, 4.50, 6),
('velvet-dream',    'Velvet Dream',    NULL,                             'Soft iris, violet, and warm musks in a velvety, dreamy embrace.',                               50, 899.00, 'EGP', 100, 0, 0, 4.65, 13),
('wild-berry',      'Wild Berry',      NULL,                             'Vibrant, playful, and juicy — blackberry, raspberry, and a fresh green finish.',               50, 899.00, 'EGP', 100, 0, 0, 4.55, 9),
('voyager',         'Voyager',         NULL,                             'Fresh, adventurous, and free. Sea spray, ginger, and cedarwood for the explorer.',             50, 899.00, 'EGP', 100, 0, 0, 4.60, 10)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ── Assign products to collections ────────────────────────────────
-- Bestsellers
INSERT IGNORE INTO `product_collections` (`product_id`, `collection_id`)
SELECT p.id, c.id FROM `products` p, `collections` c
WHERE p.`slug` IN ('carnation','ethereal-cognac','espionage','midnight-oman','baked-vanilla','euphoria','obsidian','chillwave','desire','cookies-and-cream')
AND c.`slug` = 'bestsellers';

-- New Drops
INSERT IGNORE INTO `product_collections` (`product_id`, `collection_id`)
SELECT p.id, c.id FROM `products` p, `collections` c
WHERE p.`slug` IN ('chillwave','tropica-xtreme','playboy','fruity-jungle','devils-lychee')
AND c.`slug` = 'new-drops';

-- For Him
INSERT IGNORE INTO `product_collections` (`product_id`, `collection_id`)
SELECT p.id, c.id FROM `products` p, `collections` c
WHERE p.`slug` IN ('carnation','blue-essence','chillwave','obsidian','warrior','espionage','ethereal-cognac','citranova','voyager','hetrodox','unholy','midnight-oman','minerva','val')
AND c.`slug` = 'for-him';

-- For Her
INSERT IGNORE INTO `product_collections` (`product_id`, `collection_id`)
SELECT p.id, c.id FROM `products` p, `collections` c
WHERE p.`slug` IN ('desire','devils-lychee','cloud9','baked-vanilla','cookies-and-cream','feral-rose','nude-bloom','sinful-rose','velvet-dream','wild-berry','fruity-musk','cinnamon-honey','golden-essence')
AND c.`slug` = 'for-her';

-- Date Night
INSERT IGNORE INTO `product_collections` (`product_id`, `collection_id`)
SELECT p.id, c.id FROM `products` p, `collections` c
WHERE p.`slug` IN ('carnation','obsidian','midnight-oman','espionage','ethereal-cognac','saffron-rum','sinful-rose','unholy')
AND c.`slug` = 'date-night';

-- Office Fresh
INSERT IGNORE INTO `product_collections` (`product_id`, `collection_id`)
SELECT p.id, c.id FROM `products` p, `collections` c
WHERE p.`slug` IN ('blue-essence','citranova','minerva','voyager','val','cloud9','playboy')
AND c.`slug` = 'office-fresh';

-- Summer Collection
INSERT IGNORE INTO `product_collections` (`product_id`, `collection_id`)
SELECT p.id, c.id FROM `products` p, `collections` c
WHERE p.`slug` IN ('chillwave','tropica-xtreme','sunset-swim','seaside-colada','fruity-jungle','wild-berry','fruity-musk')
AND c.`slug` = 'summer-collection';

-- Universal MUST-HAVEs
INSERT IGNORE INTO `product_collections` (`product_id`, `collection_id`)
SELECT p.id, c.id FROM `products` p, `collections` c
WHERE p.`slug` IN ('euphoria','carnation','citranova','desire','baked-vanilla','val','cloud9','blue-essence')
AND c.`slug` = 'universal';

-- Starboy Collection
INSERT IGNORE INTO `product_collections` (`product_id`, `collection_id`)
SELECT p.id, c.id FROM `products` p, `collections` c
WHERE p.`slug` IN ('obsidian','warrior','midnight-oman','unholy','espionage','golden-essence','saffron-rum','playboy')
AND c.`slug` = 'starboy';

-- ── Settings (announcement + promo) ─────────────────────────────
INSERT INTO `settings` (`key`, `value`) VALUES
('announcement_enabled', '1'),
('announcement_text',    'OUR FIRST ANNIVERSARY ✦ BUY 2 GET 2 FREE ✦ FREE DELIVERY ON ALL ORDERS'),
('promo_text',           'BUY 2 GET 2 FREE  ✦  ALL PERFUMES 899 EGP  ✦  FREE DELIVERY'),
('promo_enabled',        '1'),
('promo_min_items',      '4'),
('delivery_fee',         '0'),
('hero1_eyebrow',        'PREMIUM EGYPTIAN FRAGRANCES'),
('hero1_title',          'Wear a Scent That\nSpeaks Before\nYou Do.'),
('hero1_subtitle',       'Luxury 50ml perfumes. Inspired by the world\'s finest.'),
('hero1_btn_text',       'SHOP NOW'),
('hero1_btn_url',        '/collections.php'),
('hero2_eyebrow',        'BUY 2 GET 2 FREE'),
('hero2_title',          'Pick Any 4\nPay for Only 2.'),
('hero2_subtitle',       'Our signature deal — automatically applied at checkout.'),
('hero2_btn_text',       'SHOP THE DEAL'),
('hero2_btn_url',        '/collections.php')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
