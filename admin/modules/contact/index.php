<?php
// Display success/error messages
if(isset($_SESSION['message'])){
	echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['message']) . '</div>';
	unset($_SESSION['message']);
}

if($id && is_numeric($id)){
	$dbobj->delete('contact',$id);
	$_SESSION['message'] = "Contact message deleted successfully";
	header("Location:".BASEURL."admin/contact");
	exit;
}
	$alldata=$dbobj->fetchAll("select * from contact order by id desc");


?>
<style type="text/css">
	table{
		
		font-size: 20px;
	}
</style>


<table class="table table-bordered table-striped table-hover">
	<tr>
		<th>S.No.</th>
		<th>Name</th>
		<th>E-Mail</th>
		<th>Mobile No.</th>
		<th>Message</th>
		<th>Action</th>
	</tr>
	<tr>
		<td colspan="6"><a href="<?php echo BASEURL;?>admin/contact/create">Add New Record</a></td>
	</tr>
	<?php
	$sno=0;
	foreach ($alldata as $data) {
	?>
	<tr>
		<td><?php echo ++$sno; ?></td>
		<td><?php echo $data['name'] ?></td>
		<td><?php echo $data['email'] ?></td>
		<td><?php echo $data['mobileno'] ?></td>
		<td><?php echo $data['msg'] ?></td>
		<td>
			<a href="<?php echo BASEURL;?>admin/contact/create/<?php echo htmlspecialchars($data['id']); ?>">Edit</a>&nbsp; &nbsp; | &nbsp; &nbsp;
			<a href="#" onclick="delclick('<?php echo BASEURL;?>admin/contact/index/<?php echo htmlspecialchars($data['id']); ?>')">Delete</a>
		</td>
	</tr>
	<?php } ?>
	
</table>
<script type="text/javascript">
	function delclick(path)
	{
		if(confirm("do you want to delete this record")){
			location.href=path;
		}
	}
</script>