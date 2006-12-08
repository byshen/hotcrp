<?php 
require_once('../Code/header.inc');
$Me = $_SESSION["Me"];
$Me->goIfInvalid();
$Me->goIfNotPC('../index.php');
?>

<html>

<?php  $Conf->header("Distribution of Overall Merit Scores By Reviewer ") ?>

<body>


<?php 

$Conf->infoMsg("This table is sorted by the number of reviews, "
	       . "then the desending merit within that group "
	       . "of reviewers. It's difficult to read much "
	       . "into this since the averages depend so much on "
	       . "the number of papers reviewed." );

$Conf->infoMsg("This display only shows the distribution of the "
	       . " 'overall merit' score, and only for finalized papers "
	       );


$Conf->toggleButtonUpdate('showPC');
print "<center>";
$Conf->toggleButton('showPC',
		    "Show All Reviewers",
		    "Show Only PC Members");
print "</center>";


$result=$Conf->qe("SELECT ContactInfo.firstName, ContactInfo.lastName,"
		  . " ContactInfo.email, ContactInfo.contactId,"
		  . " AVG(PaperReview.overAllMerit) as merit, "
		  . " COUNT(PaperReview.reviewSubmitted) as count "
		  . " FROM ContactInfo "
		  . ($_REQUEST["showPC"] ? " join PCMember using (contactId) " : "")
		  . " LEFT JOIN PaperReview on (PaperReview.reviewSubmitted>0 and PaperReview.contactId=ContactInfo.contactId) "
		  . " GROUP BY ContactInfo.contactId "
		  . " HAVING COALESCE(SUM(PaperReview.reviewSubmitted),0) > 0 "
		  . " ORDER BY merit DESC, count DESC, merit DESC, ContactInfo.lastName "
		  );


if (MDB2::isError($result)) {
  $Conf->errorMsg("Error in sql " . $result->getMessage());
  exit();
} 

?>

<table border=1 align=center>
<tr bgcolor=<?php echo $Conf->contrastColorOne?>>
<th colspan=6> Reviewer Ranking By Overall Merit </th> </tr>

<tr>
<th> Row # </th>
<th> Reviewer </th>
<th> Merit </th>
</tr>
<td> <b> 
<?php
$rf = reviewForm();
$meritMax = $rf->maxNumericScore('overAllMerit');

$rowNum = 0;
while ($row=$result->fetchRow(MDB2_FETCHMODE_ASSOC)) {
  $rowNum++;

  $first=$row['firstName'];
  $last=$row['lastName'];
  $email=$row['email'];
  $contactId=$row['contactId'];

  print "<tr> <td> $rowNum </td> ";
  print "<td> ";
  print "$first $last ($email) </td> \n";

  print "<td align=center>";
  $q = "SELECT overAllMerit FROM PaperReview "
  . " WHERE contactId=$contactId "
  . " AND reviewSubmitted>0";
  $Conf->graphValues($q, "overAllMerit", $meritMax);

  print "</td>";

  print "<tr> \n";
}
?>
</table>

</body>
<?php  $Conf->footer() ?>
</html>

