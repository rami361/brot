-- prepare table
ALTER TABLE uebersicht
    ADD name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    ADD type VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    ADD ingredients_text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    ADD description_long TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    ADD description_short TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    ADD FULLTEXT (name, type, ingredients_text, description_long, description_short)


-- extract fulltext relevant fields from JSON and update the table
UPDATE uebersicht u
JOIN rezepte r ON u.rezepte_id = r.rezepte_id
SET 
    u.name = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.name')), ''),
    u.type = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.type')), ''),
    u.description_long = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.description_long')), ''),
    u.description_short = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.description_short')), ''),
    u.ingredients_text = COALESCE(
        (
            SELECT GROUP_CONCAT(JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(ingredient), '$[0]')) SEPARATOR ' ')
            FROM JSON_TABLE(
                r.rezept,
                '$.ingredients[*]' COLUMNS (
                    ingredient JSON PATH '$'
                )
            ) AS jt
            WHERE JSON_LENGTH(ingredient) > 0
        ), ''
    )
WHERE r.rezept IS NOT NULL AND JSON_VALID(r.rezept) AND u.uebersicht_id IS NOT NULL;


-- fulltext search
SELECT u.uebersicht_id, u.name, u.type, u.description_short
FROM uebersicht u
WHERE MATCH(u.name, u.type, u.ingredients_text, u.description_long, u.description_short)
      AGAINST('Almrebell Sauerteigbrot Mehl' IN BOOLEAN MODE);


-- test run
SELECT 
    u.uebersicht_id,
    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.name')), '') AS name,
    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.type')), '') AS type,
    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.description_long')), '') AS description_long,
    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.description_short')), '') AS description_short,
    COALESCE(
        (
            SELECT GROUP_CONCAT(JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(ingredient), '$[0]')) SEPARATOR ' ')
            FROM JSON_TABLE(
                r.rezept,
                '$.ingredients[*]' COLUMNS (
                    ingredient JSON PATH '$'
                )
            ) AS jt
            WHERE JSON_LENGTH(ingredient) > 0
        ), ''
    ) AS ingredients_text
FROM uebersicht u
JOIN rezepte r ON u.rezepte_id = r.rezepte_id
WHERE r.rezept IS NOT NULL AND JSON_VALID(r.rezept) AND u.uebersicht_id IS NOT NULL;


-- backup erstellen
CREATE TABLE uebersicht_backup AS SELECT * FROM uebersicht;
CREATE TABLE rezepte_backup AS SELECT * FROM rezepte;


-- extend fulltext by steps
ALTER TABLE uebersicht
ADD steps_text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
ADD FULLTEXT (steps_text);


-- extended update query
UPDATE uebersicht u
JOIN rezepte r ON u.rezepte_id = r.rezepte_id
SET 
    u.name = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.name')), ''),
    u.type = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.type')), ''),
    u.description_long = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.description_long')), ''),
    u.description_short = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.description_short')), ''),
    u.ingredients_text = COALESCE(
        (
            SELECT GROUP_CONCAT(JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(ingredient), '$[0]')) SEPARATOR ' ')
            FROM JSON_TABLE(
                r.rezept,
                '$.ingredients[*]' COLUMNS (
                    ingredient JSON PATH '$'
                )
            ) AS jt
            WHERE JSON_LENGTH(ingredient) > 0
        ), ''
    ),
    u.steps_text = COALESCE(
        (
            SELECT GROUP_CONCAT(title SEPARATOR ' ')
            FROM JSON_TABLE(
                r.rezept,
                '$.instruction_steps[*]' COLUMNS (
                    title VARCHAR(255) PATH '$.title'
                )
            ) AS jt
            WHERE title IS NOT NULL
        ), ''
    )
WHERE r.rezept IS NOT NULL AND JSON_VALID(r.rezept) AND u.uebersicht_id IS NOT NULL;