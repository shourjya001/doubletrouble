-- Add modified_by column to TOCDBE_NEW
ALTER TABLE "dbo"."TOCDBE_NEW"
ADD COLUMN IF NOT EXISTS "modified_by" VARCHAR(50);

-- Modified trigger function to log BUSLINECODE or ACRONYM changes and include modified_by from session.codusr
CREATE OR REPLACE FUNCTION dbo."trg_log_blcode_change"()
RETURNS TRIGGER AS $$
DECLARE
    v_codUsr VARCHAR(50);
BEGIN
    IF NEW."BUSLINECODE" IS DISTINCT FROM OLD."BUSLINECODE" OR NEW."ACRONYM" IS DISTINCT FROM OLD."ACRONYM" THEN
        v_codUsr := current_setting('myapp.codUsr', true);
        INSERT INTO "dbo"."TOCDBE_NEW" (
            "CODOC",
            "NAMOC",
            "BUSLINETYPE",
            "BUSLINECODE",
            "ACRONYM",
            "NEW_BUSLINECODE",
            "NEW_ACRONYM",
            "modified_by"
        )
        VALUES (
            OLD."CODOC",
            OLD."NAMOC",
            OLD."BUSLINETYPE",
            OLD."BUSLINECODE",
            OLD."ACRONYM",
            NEW."BUSLINECODE",
            NEW."ACRONYM",
            -- current_setting('session.codusr', TRUE) -- Retrieve codUsr from session parameter
            v_codUsr
        );
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Drop existing trigger if it exists
DROP TRIGGER IF EXISTS trg_blcode_change ON "dbo"."TOCDBE";

-- Create trigger on TOCDBE to capture frontend updates
CREATE TRIGGER trg_blcode_change
AFTER UPDATE ON "dbo"."TOCDBE"
FOR EACH ROW
EXECUTE FUNCTION dbo."trg_log_blcode_change"();




if (isset($codUsr)) {
    $setUserQuery = "SELECT set_config('myapp.codUsr', '$codUsr', false)";
    pg_query($setUserQuery);
}
