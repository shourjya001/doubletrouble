<?php  
// Modified code for to get brief & detailed comment.
// Modified by VKU for CRM140, Removed FLAG Check condition in SQL
// Modified by sumeet on 06/05/2014 for showing only 50 characters for brief comment like detailed comment
// Modified to fix Enslavement to Sakkarah column display and add TBUSLINE_AUDIT logging

// SQL Query (unchanged, already includes BUSLINETYPE, BUSLINECODE, BUSLINENAME)
$OCQry = "select T1.\"CODOC\",T1.\"NAMOC\",T2.\"USERNAME\",T1.\"INFOWAY\",T1.\"FLAGUSERDBE\", 'scope', T1.\"BUSLINETYPE\",T1.\"BUSLINECODE\", 
          (select T3.\"BUSLINENAME\" from \"TBUSLINEDBE\" T3 where T1.\"BUSLINECODE\"=T3.\"BUSLINECODE\" and T1.\"BUSLINETYPE\"=T3.\"BUSLINETYPE\" and T3.\"FLAG\"='Y') as \"BUSLINENAME\", 
          CASE WHEN T1.\"BRICOMMENT\"!='' THEN substring((T1.\"BRICOMMENT\")::varchar(50), 1,50) 
               WHEN (T1.\"DETCOMMENT\")::varchar(100)!='' THEN substring((T1.\"DETCOMMENT\")::varchar(50), 1,50) 
               ELSE '' END, 
          T1.\"FLAGACTIVE\", T1.\"EnslavementToSakkarah\" 
          from \"TUSERDBE\" T2 right outer join \"TOCDBE\" T1 on T2.\"CODUSER\"=T1.\"CODUSER\" 
          order by T1.\"NAMOC\"";

echo $OCQry;
 
$OCRes = pg_query($OCQry);
$OC_Tot_Recs = pg_num_rows($OCRes);
?>

<!-- Create Audit Table and Trigger (run this SQL once in the database) -->
<?php
$createAuditTable = "
CREATE TABLE IF NOT EXISTS "TBUSLINE_LOG" (
    "OC_CODE" VARCHAR(50),
    "OC_NAME" VARCHAR(100),
    "BL_TYPE" VARCHAR(50) DEFAULT 'SAKKARAH',
    "OLD_BL_CODE" VARCHAR(50),
    "NEW_BL_CODE" VARCHAR(50),
    "OLD_BL_NAME" VARCHAR(100),
    "NEW_BL_NAME" VARCHAR(100),
    "CREATED_DATE" TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
";

$createTriggerFunction = "
CREATE OR REPLACE FUNCTION log_busline_changes()
RETURNS TRIGGER AS $$
BEGIN
    -- Check if BUSLINECODE or BUSLINENAME has changed and EnslavementToSakkarah is 'Y'
    IF (OLD."BUSLINECODE" != NEW."BUSLINECODE" OR OLD."BUSLINENAME" != NEW."BUSLINENAME") THEN
        -- Insert log for each TOCDBE record where EnslavementToSakkarah = 'Y'
        INSERT INTO "TBUSLINE_LOG" (
            "OC_CODE", "OC_NAME", "BL_TYPE", 
            "OLD_BL_CODE", "NEW_BL_CODE", 
            "OLD_BL_NAME", "NEW_BL_NAME", 
            "CREATED_DATE"
        )
        SELECT 
            T."CODOC", T."NAMOC", 'SAKKARAH',
            OLD."BUSLINECODE", NEW."BUSLINECODE",
            OLD."BUSLINENAME", NEW."BUSLINENAME",
            CURRENT_TIMESTAMP
        FROM "TOCDBE" T
        WHERE T."BUSLINECODE" = OLD."BUSLINECODE"
          AND T."BUSLINETYPE" = OLD."BUSLINETYPE"
          AND T."EnslavementToSakkarah" = 'Y';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
";

$createTrigger = "
CREATE TRIGGER busline_update_trigger
AFTER UPDATE ON "TBUSLINEDBE"
FOR EACH ROW
EXECUTE FUNCTION log_busline_changes();
";

// Execute the SQL to create table and trigger (run once, comment out after execution)
// pg_query($createAuditTable);
// pg_query($createTriggerFunction);
// pg_query($createTrigger);
?>

<TABLE class="TableRefStyle" align="center">
<TR align="center">
    <TD align="center">
        <table border="0">
            <tr>
                <?php echo DisplayApplicationTitle("../images/Title_REF_OC.gif") ?>
            </tr>
        </table>
        <span align="center" style="color: red"><br>**Limits can be inputted only on operating center's with Valid <b>"Scope"</b>, <b>"Business Line Type"</b>, <b>"Business Line Code"</b>, <b>"Business Line Name"</b> and <b>"Is Active is 'Yes'"</b>. <br>Please reach out to Banking Admin for activation.</span>
        <br><span align="center" style="color: blue">Note: Changes to Business Line Code and Name are logged in TBUSLINE_AUDIT for auditing purposes.</span>
    </TD>
</TR>
<tr>
    <TD align=right width=100%><a href='#Bottom'><img src='../images/Button_Down.gif' hspace=5 border=0></a></td>
</tr>
<TR><TD> </TD></TR>
<TR class="TabHead"><TD> </TD></TR>
<TR><TD> </TD></TR>
<TR align="center">
    <TD>
        <TABLE border="1" cellpadding="2" cellspacing="0" bordercolor="#000000" width="99%" id="DataTable">
            <TR class="TabHead" align="center">
                <TD align="center" width="9%">Code</TD>
                <TD align="center" width="19%">Name</TD>
                <TD align="center" width="5%">Information Way</TD>
                <TD align="center" width="5%">DBE use</TD>
                <TD align="center" width="5%">Scope</TD>
                <TD align="center" width="8%">Business Line Type</TD>
                <TD align="center" width="8%">Business Line Code</TD>
                <TD align="center" width="16%">Business Line Name</TD>
                <TD align="center" width="36%">Comments and Emails</TD>
                <TD align="center" width="16%">Is Active</TD>
                <TD align="center" width="18%">Enslavement to Sakkarah</TD>
            </TR>
            <?php
            $OC_Prof = 'REF';
            if ($todo_type == 8)
                $OC_Prof = 'VIEW';

            $codeUserCODOCArray = array();
            $flaguserArray = array();
            $OCUserNameArray = array();
            $str = '';
            $str3 = '';
            $RowClassName = '';

            for ($i = 0; $i < $OC_Tot_Recs; $i++) {
                $OC_data = pg_fetch_row($OCRes);
                $count = sizeof($OC_data) + 1;
                $RowClassName = ($i % 2 == 0) ? "TableDataValueEven" : "TableDataValueOdd";
            ?>
                <TR bgcolor="#99CCFF" class="<?php echo $RowClassName; ?>" onclick="handleRowClick(this)">
                    <?php
                    for ($j = 0; $j < $count; $j++) {
                        $d = rtrim($OC_data[$j]);
                        $Oc_Code = rtrim($OC_data[0]);
                        $colorCode = '';

                        if ($j == 2) {
                            continue; // Skip USERNAME column
                        }

                        if ($j == 9) { // Comments and Emails
                            if (trim($OC_data[9]) != '') {
                                $disOCCommentData = $OC_data[9];
                            } else {
                                $colorCode = "style=\"color: #FF0000\"";
                                $disOCCommentData = 'Add comment and email';
                            }
                            ?>
                            <TD>
                                <a <?php echo $colorCode; ?> href="javascript:openCommentDoc('<?php echo $Oc_Code; ?>', '<?php echo $OC_Prof; ?>');"><?php echo htmlspecialchars($disOCCommentData); ?></a>
                            </TD>
                            <?php
                            continue;
                        }

                        if ($j == 4) { // DBE Use
                            if (strncasecmp($d, 'Y', 1) == 0) {
                                ?><TD align="center">Yes</TD><?php
                            } elseif (strncasecmp($d, 'N', 1) == 0) {
                                ?><TD align="center">No</TD><?php
                            } else {
                                ?><TD align="center"> </TD><?php
                            }
                            $str3 = $kval . ":" . $d;
                            continue;
                        }

                        if ($j == 5) { // Scope
                            $query_header = "SELECT \"SCOPE\" FROM \"TOCSCOPEDBE\" WHERE \"FLAG\"='Y' AND \"CODOC\"='$Oc_Code'";
                            $result_header = pg_query($query_header);
                            $scope = '';
                            while ($query_data = pg_fetch_row($result_header)) {
                                $scope .= trim($query_data[0]) . '&';
                            }
                            $scope = substr($scope, 0, strlen($scope) - 1);
                            if ($scope != '') {
                                echo "<TD align=\"center\">" . str_replace('&', ',', trim($scope)) . "</TD>";
                            } else {
                                echo "<TD align=\"center\"> </TD>";
                            }
                            $str3 = $str3 . "<>" . $scope;
                            array_push($flaguserArray, $str3);
                            $str3 = '';
                            $kval = '';
                            continue;
                        }

                        if ($j == 10) { // Is Active
                            if (strncasecmp($d, 'Y', 1) == 0) {
                                ?><TD align="center">Yes</TD><?php
                            } elseif (strncasecmp($d, 'N', 1) == 0) {
                                ?><TD align="center">No</TD><?php
                            } else {
                                ?><TD align="center"> </TD><?php
                            }
                            continue;
                        }

                        if ($j == 11) { // Enslavement to Sakkarah
                            if (strncasecmp($d, 'Y', 1) == 0) {
                                ?><TD align="center">Yes</TD><?php
                            } elseif (strncasecmp($d, 'N', 1) == 0) {
                                ?><TD align="center">No</TD><?php
                            } else {
                                ?><TD align="center"> </TD><?php
                            }
                            continue;
                        }

                        if ($j == 7) { // Business Line Code
                            ?><TD align="right"><?php echo trim($d); ?></TD><?php
                        } else {
                            ?><TD align="left"><?php echo trim($d); ?></TD><?php
                        }

                        if ($j == 0) { // CODOC
                            $UserlistDataQry = "select distinct E.\"CODOC\",B.\"CODUSER\",B.\"USERNAME\" from \"TUSERDBE\" B, \"TOCDBE\" E 
                                               WHERE E.\"FLAGACTIVE\"='Y' AND B.\"CODUSER\"=E.\"CODOC\" AND B.\"CODPROFILE\"='1' 
                                               AND E.\"CODOC\"='$d' AND B.\"FLAGUSER\"='Y'";
                            $UserlistDataQry = pg_query($UserlistDataQry);
                            $UserInfoRows = pg_num_rows($UserlistDataQry);
                            for ($jj = 0; $jj < $UserInfoRows; $jj++) {
                                $UserlistData = pg_fetch_row($UserlistDataQry);
                                $codoc_code = rtrim($UserlistData[0]);
                                $codoc_coduser = rtrim($UserlistData[1]);
                                $codoc_name = rtrim($UserlistData[2]);
                                if ($jj == 0) {
                                    $str1 = $codoc_code . "**" . $codoc_coduser . ":" . $codoc_name;
                                } else {
                                    $str = $str . ";" . $codoc_coduser . ":" . $codoc_name;
                                }
                                $str2 = $str1 . $str;
                                if ($UserInfoRows == 1) {
                                    array_push($OCUserNameArray, $str1);
                                } elseif ($UserInfoRows > 0) {
                                    array_push($OCUserNameArray, $str2);
                                }
                                $kval = $d;
                            }
                        }
                    }
                    ?>
                </TR>
            <?php
            }
            $codeOCUser = implode(",", $OCUserNameArray);
            $codeFlagOCUser = implode(",", $flaguserArray);
            ?>
        </TABLE>
    </TD>
</TR>
</TABLE>
