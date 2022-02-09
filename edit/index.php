<!DOCTYPE html>
<?php
	require_once "../common.php";

	// Assemble plan data for use by JavaScript

	if (isset($_GET["plan"])) { // Load a plan from the database
		require_login();
		$plan_id = (int) $_GET["plan"];
		// TODO: Join degree table for name/year - maybe shouldn't have two separate queries
		if ($_SESSION["permissions"] > 0) { // Staff - all staff can view all student plans
			$planQuery = $db->query("SELECT * FROM plan WHERE plan_id = ?", [$plan_id]);
		}
		else { // Student - students can only view their own plans
			$planQuery = $db->query("SELECT * FROM plan WHERE plan_id = ? AND user_id = ?", [$plan_id, $_SESSION["user_id"]]);
		}
		if (count($planQuery) != 1) crash(ErrorCode::PlanNotExist, [$plan_id, $_SESSION["user_id"]]);
		$planRow = $planQuery[0]; // Only one row

		// Get the plan's semesters and transfer credits
		$plan = json_decode($planRow["json"], true);

		$plan["plan_id"] = $plan_id;
		$plan["plan_title"] = $planRow["plan_title"];
		$plan["degree_id"] = $planRow["degree_id"];
	}

	else if (isset($_GET["major"]) && isset($_GET["year"])) { // Guest mode create an unsavable empty plan
		// Populate the plan with empty semesters for the next 4 years
		$semesters = [];
		$startYear = intval($_GET["year"]);
		for ($year = $startYear; $year < $startYear + 4; $year++) {
			$semesters[] = ["year" => $year, "season" => FALL, "courses" => []];
			$semesters[] = ["year" => $year+1, "season" => SPRING, "courses" => []];
		}
		$plan = ["semesters" => $semesters, "transfer_bank" => []];

		$plan["plan_title"] = "Guest mode";
		$plan["degree_id"] = find_degree_id($_GET["major"], $_GET["year"]);
	}
	else {
		crash(ErrorCode::NoPlanSpecified);
	}

	$degree = $db->query("SELECT major, year FROM degree WHERE degree_id = ?", [$plan["degree_id"]])[0];
	$plan["degree_major"] = $degree["major"];
	$plan["degree_year"] = $degree["year"];

	// Load all courses for this degree
	// TODO: Prereqs and coreqs
	$courses = $db->query("SELECT * FROM degree_join_course JOIN course USING (course_id) WHERE degree_id = ?", [$plan["degree_id"]]);
?>
<html>
<head>
	<meta charset="utf-8">
	<title>CourseCorrect</title>
	<link rel="icon" href="../favicon.ico">
  	<meta name="viewport" content="width=device-width, initial-scale=1">

	<!-- Libraries -->
	<link rel="stylesheet" href="../libs/bootstrap.min.css">
	<script src="../libs/jquery.slim.min.js"></script>
	<script src="../libs/popper.min.js"></script>
	<script src="../libs/bootstrap.min.js"></script>
	<script src="../libs/svg.min.js"></script>
	<link rel="stylesheet" href="../libs/fontawesome.min.css">
	<script type="text/javascript" src="../libs/redips-drag.min.js"></script>

	<!-- Application code and style -->
	<link rel="stylesheet" href="style.css">
	<script type="text/javascript" src="Executive.js"></script>
	<script type="text/javascript" src="ArrowRender.js"></script>
	<script type="text/javascript" src="Plan.js"></script>
	<script type="text/javascript" src="Semester.js"></script>
	<script type="text/javascript" src="Course.js"></script>
	<script>
		window.addEventListener('DOMContentLoaded', e => {
			window.executive = new Executive(<?=json_encode($courses)?>, <?=json_encode($plan)?>);
		});
	</script>
</head>
<body>
	<div id="alert_holder"></div>

	<header class="container-fluid py-3">
		<div class="row">
			<div class="col-sm-4">
				<a href="https://ku.edu/"><img src="../images/KUSig_Horz_Web_Blue.png" class="KU_image pt-2 ml-2"></a>
			</div>
			<div class="col-sm-4 text-sm-center KU_color_text">
				<h1><a href="../list">CourseCorrect</a></h1>
			</div>
			<div class="col-sm-4 text-right">
				<!--Student info-->
				<div class="d-inline-block text-left">
					<span class="students_info">Name TODO</span><br>
					<span class="students_info" id="degree_title"></span><br>
					<span class="students_info"><?=isset($_SESSION["user_id"]) ? ("Student ID: " . $_SESSION["kuid"]) : "Not logged in"?></span>
				</div>
				<img src="../images/ku_jayhawk_2.jpg" class="profile_picture align-top no-print">
			</div>
		</div>
	</header>

	<!-- Navigation bar -->
	<nav class="navbar navbar-expand-sm navbar-dark KU_color_background">
		<a class="navbar-brand" href="index.html">Home</a>
		<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
			<span class="navbar-toggler-icon"></span>
		</button>
		<div class="collapse navbar-collapse" id="collapsibleNavbar">
			<ul class="navbar-nav">
				<li class="nav-item">
			  		<a class="nav-link" href="http://classes.ku.edu">Schedule of Classes</a>
				</li>
				<li class="nav-item">
			  		<a class="nav-link" href="http://vsb.ku.edu">Visual Schedule Builder</a>
				</li>
				<li class="nav-item">
			  		<a class="nav-link" href="http://sa.ku.edu">Enroll & Pay</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="https://my.ku.edu/uPortal/">myKU</a>
				</li>
		  	</ul>
		</div>
		<span class="float-right">
			<span id="save-container">
				<!-- TODO: Decide if renaming should make a new plan (disabled for now) -->
				<input type="text" id="plan_title" class="form-control form-control-sm" placeholder="Plan name...">
				<!--Save button-->
				<a id="save-button" type="button" class="btn btn-light btn-sm">Save <i class="fa fa-save"></i></a>
			</span>
			<!--Print button-->
			<a href="javascript:window.print()" type="button" class="btn btn-light btn-sm">Print <i class="fa fa-print"></i></a>
		</span>
	</nav>

	<!--Printing only content (reformatted notifications and other courses)-->
	<div class="container only_print">
		<div class="row mt-3">
			<div class="col-sm-6">
				<h3>Notifications</h3>
				<div class="bg-light border p-3">
					<ul id="print-notifications"></ul>
				</div>
			</div>
			<div class="col-sm-6">
				<h3>Excluded courses</h3>
				<p id="print-course-bank"></p>

				<h3>Transferred courses</h3>
				<p id="print-transfer-bank"></p>
			</div>
		</div>
	</div>
	
	<!--Content-->
	<div class="container">
		<div id="redips-drag" class="row">
			<div class="col-lg-4 no-print">
				<div class="my-4">
					<h3>Course Bank</h3>
					<table id="course-bank" class="overflow-auto p-3 mb-3 mb-md-0 mr-md-3 bg-light border" style="min-width: 250px; min-height: 100px;">
						<tr><td></td></tr>
					</table>
				</div>
				
				<div class="mb-4">
					<h3>Transfer Credits</h3>
					<table id="transfer-bank" class="overflow-auto p-3 mb-3 mb-md-0 mr-md-3 bg-light border" style="min-width: 250px; min-height: 60px;">
						<tr><td></td></tr>
					</table>
				</div>
				
				<div class="mb-4" id="add_extra_course_box">
					<h3>Add Extra Course</h3>
					<div class="row mr-2">
						<label for="course_code" class="col-sm-5 col-form-label">Course Code:</label>
						<div class="col-sm-7">
							<input type="text" class="form-control" id="course_code">
						</div>
					</div>
					<div class="row mr-2">
						<label for="credit_hours" class="col-sm-5 col-form-label">Credit Hours:</label>
						<div class="col-sm-7">
							<div class="input-group">
								<input type="number" class="form-control" id="credit_hours" name="credit_hours" min="0">
								<div class="input-group-append">
									<button type="submit" class="btn btn-primary" id="course_add_submit">Add</button>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="mb-4"> <!--Errors and notifications-->
					<h3>Notifications</h3>
					<div class="overflow-auto p-3 mb-3 mb-md-0 mr-md-3 bg-light border scrollable_box">
						<ul id="notifications"></ul>
					</div>
				</div>
			</div>

			<div class="col-lg-8 mt-4">
				<div class="d-flex">
					<div id="schedule-container" class="bg-light"> <!--Schedule-->
						<div id="arrows"></div><!--Will contain the SVG with the arrows-->
						<table id="course-grid" class="border"></table><!--Will contain the drag-and-droppable courses-->
					</div>
				</div>
				
				<div class="modal fade" id="confirm-delete-plan">
					<div class="modal-dialog">
						<div class="modal-content">
							<div class="modal-header">Delete Plan</div>
							<div class="modal-body">Are you sure you wish to delete this plan?</div>
							<div class="modal-footer">
								<input type="button" class="btn btn-secondary" data-dismiss="modal" value="Cancel">
								<input type="button" class="btn btn-danger" id="delete-plan" data-dismiss="modal" value="Delete">
							</div>
						</div>
					</div>
				</div>
				
				<div class="row mt-2 no-print" id="add-semester">
					<div class="col-sm-6 offset-sm-3 border p-3 bg-light">
						<div class="input-group">
							<select id="addSemesterSelect" class="form-control">
								<option disabled selected value="-1">Choose a semester...</option>
							</select>
							<div class="input-group-append">
								<button type="button" class="btn btn-primary" id="add-semester-btn">Add semester</button>
							</div>
						</div>
					</div>
				</div>

				<div class="row mt-5 no-print">
					<div class="col-sm-6">
						<h3>KU Core links</h3>
						<div class="overflow-auto p-3 mb-3 mb-md-0 mr-md-3 bg-light border scrollable_box">
							<ul>
								<li><a href="https://kucore.ku.edu/courses">List of all approved courses</a></li>
								<li><a href="https://college.ku.edu/winter">Winter break courses</a></li>
							</ul>
						</div>
					</div>
					<div class="col-sm-6">
						<h3>EECS links</h3>
						<div class="overflow-auto p-3 mb-3 mb-md-0 mr-md-3 bg-light border scrollable_box">
							<ul>
								<li><a href="http://eecs.ku.edu/eecs-courses">List of all EECS courses</a></li>
								<li><a href="http://eecs.ku.edu/current-students/undergraduate">Undergraduate handbook</a></li>
								<li><a href="https://catalog.ku.edu/engineering/electrical-engineering-computer-science/bs-computer-science/">Description of the Bachelor of Science in Computer Science</a></li>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<footer class="pt-2 my-2 border-top text-center">
		<a href="https://github.com/ku-coursecorrect/coursecorrect">CourseCorrect</a> Copyright &copy; 2022: Drake Prebyl, James Kraijcek, Rafael Alaras, Reece Mathews, Tiger Ruan
		<br>
		View <a href="README.md">readme</a> for works cited | <a href="documentation/index.html">Documentation</a> | <a href="tests.html">Tests</a>
	</footer>
</body>
</html>