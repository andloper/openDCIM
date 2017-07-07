<?php
/*	Template file for creating Excel based reports
	
	Basically just the setup of the front page for consistency
*/

	require_once "db.inc.php";
	require_once "facilities.inc.php";
	require_once "vendor/autoload.php";

    $person = People::Current();
    if ( ! $person->ReadAccess ) {
        header('Location: '.redirect());
        exit;
    }

    if ( isset($_REQUEST['projectid']) && $_REQUEST['projectid'] != "" ) {
        if ( $_REQUEST['projectid'] == "all" ) {
            $prList = Projects::getProjectList();
            $criteriaRemark = " ".__("All Projects/Services");
        } else {
            $p = new Projects();
            $p->ProjectID = $_REQUEST['projectid'];
            $prList = $p->Search();
            $criteriaRemark = " ".__("Specified Project/Service");
        }

        $columnList = array( "DataCenter"=>"A", "Location"=>"B", "Position"=>"C", "Height"=>"D", "Label"=>"E", 
            "SerialNo"=>"F", "AssetTag"=>"G", "DeviceType"=>"H", "Template"=>"I", "Owner"=>"J", "Status"=>"K", "Tags"=>"L" );
        $DCAList = DeviceCustomAttribute::GetDeviceCustomAttributeList();

        // In case we tweak the number of base columns before the Custom Attributes, this keeps us from having to update the math
        $columnNum = count($columnList);
        foreach( $DCAList as $dca ) {
            $colName = getNameFromNumber(++$columnNum);
            $labelName = $dca->Label;
            $columnList[$labelName] = $colName;
        }

        // Go ahead and pull in a few lookup tables to memory as arrays indexed by the ID
        $deptList = Department::GetDepartmentListIndexedbyID();
        $dcList = DataCenter::GetDCList( true );
        $manList = Manufacturer::GetManufacturerList( true );
        $cabList = Cabinet::ListCabinets( false, true );
        $tmpList = DeviceTemplate::GetTemplateList( true );

    	$workBook = new PHPExcel();
    	
    	$workBook->getProperties()->setCreator("openDCIM");
    	$workBook->getProperties()->setLastModifiedBy("openDCIM");
    	$workBook->getProperties()->setTitle("Data Center Inventory Export");
    	$workBook->getProperties()->setSubject("Data Center Inventory Export");
    	$workBook->getProperties()->setDescription("Export of the openDCIM database based upon user filtered criteria.");
    	
    	// Start off with the TPS Cover Page

    	$workBook->setActiveSheetIndex(0);
    	$sheet = $workBook->getActiveSheet();

        $sheet->SetTitle('Front Page');
        // add logo
        $objDrawing = new PHPExcel_Worksheet_Drawing();
        $objDrawing->setWorksheet($sheet);
        $objDrawing->setName("Logo");
        $objDrawing->setDescription("Logo");
        $apath = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
        $objDrawing->setPath($apath . $config->ParameterArray['PDFLogoFile']);
        $objDrawing->setCoordinates('A1');
        $objDrawing->setOffsetX(5);
        $objDrawing->setOffsetY(5);

        $logoHeight = getimagesize( $apath . $config->ParameterArray['PDFLogoFile']);
        $sheet->getRowDimension('1')->setRowHeight($logoHeight[1]);

        // set the header of the print out
        $header_range = "A1:B2";
        $fillcolor = $config->ParameterArray['HeaderColor'];
        $fillcolor = (strpos($fillcolor, '#') == 0) ? substr($fillcolor, 1) : $fillcolor;
        $sheet->getStyle($header_range)
            ->getFill()
            ->getStartColor()
            ->setRGB($fillcolor);

        $org_font_size = 20;
        $sheet->setCellValue('A2', $config->ParameterArray['OrgName']);
        $sheet->getStyle('A2')
            ->getFont()
            ->setSize($org_font_size);
        $sheet->getStyle('A2')
            ->getFont()
            ->setBold(true);
        $sheet->getRowDimension('2')->setRowHeight($org_font_size + 2);
        $sheet->setCellValue('A4', 'Report generated by \''
            . $person->UserID
            . '\' on ' . date('Y-m-d H:i:s'));

        // Add text about the report itself
        $sheet->setCellValue('A7', 'Notes');
        $sheet->getStyle('A7')
            ->getFont()
            ->setSize(14);
        $sheet->getStyle('A7')
            ->getFont()
            ->setBold(true);

        $remarks = array( __("Report of all cabinets and devices associated with Projects/Services."),
            __("Criteria for Report:").$criteriaRemark  );
        $max_remarks = count($remarks);
        $offset = 8;
        for ($idx = 0; $idx < $max_remarks; $idx ++) {
            $row = $offset + $idx;
            $sheet->setCellValueExplicit('B' . ($row),
                $remarks[$idx],
                PHPExcel_Cell_DataType::TYPE_STRING);
        }
        $sheet->getStyle('B' . $offset . ':B' . ($offset + $max_remarks - 1))
            ->getAlignment()
            ->setWrapText(true);
        $sheet->getColumnDimension('B')->setWidth(120);
        $sheet->getTabColor()->setRGB($fillcolor);

        // Now the real data for the report

        foreach( $prList as $p ) {
            $prCabList = ProjectMembership::getProjectCabinets( $p->ProjectID );
            $prDevList = ProjectMembership::getProjectMembership( $p->ProjectID );

            if ( sizeof( $prCabList ) + sizeof( $prDevList ) > 0 ) {
                $sheet = $workBook->createSheet();
                $sheet->setTitle( $p->ProjectName );

                $sheet->setCellValue( 'A1', __("Project Name:"));
                $sheet->setCellValue( 'B1', $p->ProjectName );
                $sheet->setCellValue( 'A2', __("Project Sponsor:"));
                $sheet->setCellValue( 'B2', $p->ProjectSponsor );
                $sheet->setCellValue( 'A3', __("Start Date:"));
                $sheet->setCellValue( 'B3', mangleDate( $p->ProjectStartDate ));
                $sheet->setCellValue( 'A4', __("Expiration Date:"));
                $sheet->setCellValue( 'B4', mangleDate( $p->ProjectExpirationDate ));
                $sheet->setCellValue( 'A5', __("Actual End:"));
                $sheet->setCellValue( 'B5', mangleDate( $p->ProjectActualEndDate ));

                $currRow = 7;

                if ( count( $prCabList ) > 0 ){
                    $sheet->setCellValue( 'A'.$currRow, __("Total Assigned Cabinets:"));
                    $sheet->setCellValue( 'B'.$currRow, count( $prCabList ));
                    $currRow++;

                    $sheet->setCellValue( 'A'.$currRow, __("Cabinet"));
                    $sheet->setCellValue( 'B'.$currRow, __("Data Center"));

                    $currRow++;

                    foreach( $prCabList as $cab ) {
                        $sheet->setCellValue( 'A'.$currRow, $cab->Location );
                        $sheet->setCellValue( 'B'.$currRow, $dcList[$cab->DataCenterID]->Name);

                        $currRow++;
                    }
                }

                if ( count( $prDevList ) > 0 ) {
                    $currRow += 2;

                    foreach( $columnList as $fieldName=>$columnName ) {
                        $cellAddr = $columnName.$currRow;
          
                        $sheet->setCellValue( $cellAddr, $fieldName );
                    }
                    
                    $currRow++;

                    foreach( $prDevList as $dev ) {
                        foreach( $dev as $prop => $val ) {
                            if ( array_key_exists( $prop, $columnList )) {
                                $sheet->setCellValue( $columnList[$prop].$currRow, $val );
                            }
                        }

                        $sheet->setCellValue( $columnList["DataCenter"].$currRow, $dcList[$cabList[$dev->Cabinet]->DataCenterID]->Name );
                        if ( $dev->Cabinet > 0 ) {
                            $sheet->setCellValue( $columnList["Location"].$currRow, $cabList[$dev->Cabinet]->Location );
                        } else {
                            $sheet->setCellValue( $columnList["Location"].$currRow, __("Storage Room"));
                        }
                        
                        if ( $dev->TemplateID > 0 ) {
                            $sheet->setCellValue( $columnList["Template"].$currRow, $manList[$tmpList[$dev->TemplateID]->ManufacturerID]->Name . " - " . $tmpList[$dev->TemplateID]->Model );
                        } else {
                            $sheet->setCellValue( $columnList["Template"].$currRow, __("Unspecified"));
                        }

                        $sheet->setCellValue( $columnList["Owner"].$currRow, $deptList[$dev->Owner]->Name );
                        $sheet->setCellValue( $columnList["Tags"].$currRow, implode(",", $dev->GetTags()) );

                        $currRow++;
                    }
                }

                // Set all of the columns to auto-size
                foreach( $columnList as $i => $v ) {
                    $sheet->getColumnDimension($v)->setAutoSize(true);
                }
            }
        }
    	
    	// Now finalize it and send to the client

    	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    	header( sprintf( "Content-Disposition: attachment;filename=\"opendcim-%s.xlsx\"", date( "YmdHis" ) ) );
    	
    	$writer = new PHPExcel_Writer_Excel2007($workBook);
    	$writer->save('php://output');
    } else {
        $pList = Projects::getProjectList();
?>
<!doctype html>
<html>
<head>
  <meta http-equiv="X-UA-Compatible" content="IE=Edge">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <link rel="stylesheet" href="css/inventory.php" type="text/css">
  <link rel="stylesheet" href="css/print.css" type="text/css" media="print">
  <link rel="stylesheet" href="css/jquery-ui.css" type="text/css">
  <style type="text/css"></style>
  <!--[if lt IE 9]>
  <link rel="stylesheet"  href="css/ie.css" type="text/css" />
  <![endif]-->
  <script type="text/javascript" src="scripts/jquery.min.js"></script>
  <script type="text/javascript" src="scripts/jquery-ui.min.js"></script>
</head>
<body>
<?php include( 'header.inc.php' ); ?>
    <div class="page">
<?php
    include('sidebar.inc.php');
echo '      <div class="main">
            <form>
            <label for="projectid">',__("Project Name:"),'</label>
            <select name="projectid" id="projectid" onchange="this.form.submit()">
                <option value="">',__("Select Project"),'</option>
                <option value="all">',__("All Projects"),'</option>';
foreach($pList as $p){print "\t\t\t\t<option value=\"$p->ProjectID\">$p->ProjectName</option>\n";} ?>
            </select>
            </form>
            <br><br>
            <div>
            <p><?php print __("Choose a specific project to report on, or all projects.  Output will be sent in Excel 2007 format."); ?></p>
            </div>
        </div><!-- END div.main -->
    </div><!-- END div.page -->
</body>
</html>
<?php

    }
?>