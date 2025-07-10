('EnslavementToSakkarah:', obj.cells[10].firstChild.nodeValue)


CREATE OR REPLACE FUNCTION dbo."SPU_WK_RES_TOCDBE"()
RETURNS void
LANGUAGE plpgsql
AS $function$
DECLARE
    Error INTEGER;
    CDENJU VARCHAR(5);
    CDELST VARCHAR(5);
    LILOEA VARCHAR(35);
    SIELST VARCHAR(30);
    CODOC VARCHAR(10);
    CDBULI VARCHAR(6);
    BUSLINE VARCHAR(15);
    BUSLINETYP_EX VARCHAR(15);
    BUSLINECODE_EX VARCHAR(15);
    TODAYTIME TIMESTAMP;
    ENSLAVEMENT VARCHAR(1); -- Added to store EnslavementToSakkarah value
    WK_RES_TOCDBE_DEACTIVE CURSOR FOR
        SELECT "WKOC"."CDENJU", "WKOC"."CDELST", trim("WKOC"."LILOES"), 
               coalesce(nullif(trim("WKOC"."LILOEA"),''), trim("WKOC"."SIELST")), 
               "OC"."CODOC", LOCALTIMESTAMP
        FROM "dbo"."WK_RES_TOCDBE" "WKOC"
        RIGHT JOIN "dbo"."TOCDBE" "OC" ON COALESCE("WKOC"."CDENJU", '') || COALESCE("WKOC"."CDELST", '') = "OC"."CODOC"
        WHERE "OC"."FLAGACTIVE" = 'Y' 
        AND NULLIF(COALESCE("WKOC"."CDENJU", '') || COALESCE("WKOC"."CDELST", ''), '') IS NULL;
    WK_RES_TOCDBE_BUSLINE_UPDATE CURSOR FOR
        SELECT "WKOC"."CDENJU", "WKOC"."CDELST", 
               coalesce(nullif(trim("WKOC"."LILOEA"),''), trim("WKOC"."SIELST")), 
               trim("WKOC"."SIELST"), trim("WKOC"."CDBULI"), 
               "OC"."CODOC", "OC"."BUSLINECODE"
        FROM "dbo"."WK_RES_TOCDBE" "WKOC"
        LEFT JOIN "dbo"."TOCDBE" "OC" ON COALESCE("WKOC"."CDENJU", '') || COALESCE("WKOC"."CDELST", '') = "OC"."CODOC"
        WHERE "OC"."FLAGACTIVE" = 'Y'
        AND NULLIF(COALESCE("WKOC"."CDENJU", '') || COALESCE("WKOC"."CDELST", ''), '') IS NOT NULL;
    WK_RES_TOCDBE_NEW CURSOR FOR
        SELECT "WKOC"."CDENJU", "WKOC"."CDELST", 
               coalesce(nullif(trim("WKOC"."LILOEA"),''), trim("WKOC"."LILOES")), 
               trim("WKOC"."SIELST"), trim("WKOC"."CDBULI"), "OC"."CODOC"
        FROM "dbo"."WK_RES_TOCDBE" "WKOC"
        LEFT JOIN "dbo"."TOCDBE" "OC" ON COALESCE("WKOC"."CDENJU", '') || COALESCE("WKOC"."CDELST", '') = "OC"."CODOC"
        WHERE NULLIF("OC"."CODOC", '') IS NULL;
BEGIN
    SWV_error := 0;

    -- DECLARATION OF THE CURSOR FOR DEACTIVE
    OPEN WK_RES_TOCDBE_DEACTIVE;
    FETCH WK_RES_TOCDBE_DEACTIVE INTO CDENJU, CDELST, LILOEA, SIELST, CODOC, TODAYTIME;
    WHILE (FOUND) LOOP
        -- Check EnslavementToSakkarah before updating
        SELECT "EnslavementToSakkarah" INTO ENSLAVEMENT FROM "dbo"."TOCDBE" WHERE "CODOC" = CODOC;
        IF ENSLAVEMENT = 'Y' THEN
            -- CALL SPI_DEACTOC_TLIMDELDBE.SQL
            PERFORM "dbo"."SPI_DEACTOC_TLIMDELDBE"(CODOC);
            -- Update the TOCDBE
            UPDATE "dbo"."TOCDBE"
            SET "FLAGACTIVE" = 'N', "FLAGUSERDBE" = 'N', "OCDEACTDATE" = TODAYTIME
            WHERE "CODOC" = CODOC;
            raise notice 'Deactivated CODOC: %', CODOC;
        ELSE
            raise notice 'Skipped deactivation for CODOC: % (EnslavementToSakkarah is not Y)', CODOC;
        END IF;
        FETCH WK_RES_TOCDBE_DEACTIVE INTO CDENJU, CDELST, LILOEA, SIELST, CODOC, TODAYTIME;
    END LOOP;
    CLOSE WK_RES_TOCDBE_DEACTIVE;

    -- DECLARATION OF THE CURSOR FOR BUSLINECODE UPDATE
    OPEN WK_RES_TOCDBE_BUSLINE_UPDATE;
    FETCH WK_RES_TOCDBE_BUSLINE_UPDATE INTO CDENJU, CDELST, LILOEA, SIELST, CDBULI, CODOC, BUSLINE;
    WHILE (FOUND) LOOP
        -- Check EnslavementToSakkarah before updating
        SELECT "EnslavementToSakkarah" INTO ENSLAVEMENT FROM "dbo"."TOCDBE" WHERE "CODOC" = CODOC;
        IF ENSLAVEMENT = 'Y' THEN
            -- Check the BUSLINECODE is not empty and valid
            IF (trim(CDBULI) <> '') THEN
                raise notice 'select 2..%,%', CODOC, CDBULI;
                IF EXISTS (SELECT "BUSLINECODE" FROM "dbo"."TBUSLINEDBE" WHERE "BUSLINECODE" = trim(CDBULI)) THEN
                    SELECT "BUSLINETYPE" INTO BUSLINETYP_EX FROM "dbo"."TBUSLINEDBE" WHERE "BUSLINECODE" = trim(CDBULI);
                    UPDATE "dbo"."TOCDBE"
                    SET "BUSLINECODE" = trim(CDBULI), "BUSLINETYPE" = BUSLINETYP_EX
                    WHERE "CODOC" = CODOC;
                    raise notice 'select 1..%,%', CODOC, CDBULI;
                END IF;
            END IF;
            IF EXISTS (SELECT "BUSLINECODE" FROM "dbo"."TBUSLINEDBE" WHERE "BUSLINECODE" = trim(CDBULI)) OR (trim(CDBULI) = '') THEN
                raise notice 'CDBULI..%', CDBULI;
                UPDATE "dbo"."TOCDBE" 
                SET "NAMOC" = trim(LILOEA)
                WHERE "CODOC" = CODOC;
                raise notice 'LILOEA..%', LILOEA;
            END IF;
        ELSE
            raise notice 'Skipped update for CODOC: % (EnslavementToSakkarah is not Y)', CODOC;
        END IF;
        FETCH WK_RES_TOCDBE_BUSLINE_UPDATE INTO CDENJU, CDELST, LILOEA, SIELST, CDBULI, CODOC, BUSLINE;
    END LOOP;
    CLOSE WK_RES_TOCDBE_BUSLINE_UPDATE;

    -- DECLARATION OF THE CURSOR FOR NEW RECORDS (UNCHANGED)
    OPEN WK_RES_TOCDBE_NEW;
    -- ... (rest of the code for inserting new records remains unchanged)
END;
$function$
