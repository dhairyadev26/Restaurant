<?php
// Display success/error messages
if(isset($_SESSION['message'])){
	echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['message']) . '</div>';
	unset($_SESSION['message']);
}

if($id && is_numeric($id)){	
	$dbobj->delete('services',$id);
	$_SESSION['message'] = "Service deleted successfully";
	header("Location:".BASEURL."admin/services");
	exit;
}
	$alldata=$dbobj->fetchAll("select * from services order by id desc");

?>
<style type="text/css">
	table{
		font-size: 20px;
	}
</style>



<table class="table table-bordered table-striped table-hover">
	<tr>
		<th>S.No.</th>
		<th>Icon</th>
		<th>Description</th>
		<th>Action</th>
	</tr>
	<tr>
		<td colspan="4"><a href="<?php echo BASEURL;?>admin/services/create">Add New Record</a></td>
	</tr>
	<?php
	$sno=0;
	foreach ($alldata as $data) {
	?>
	<tr>
		<td><?php echo ++$sno; ?></td>
		<td><?php echo $data['icon'] ?></td>
		<td><?php echo $data['description'] ?></td>
		<td>
			<a href="<?php echo BASEURL;?>admin/services/create/<?php echo htmlspecialchars($data['id']); ?>">Edit</a>&nbsp; &nbsp; | &nbsp; &nbsp;
			<a href="#" onclick="delclick('<?php echo BASEURL;?>admin/services/index/<?php echo htmlspecialchars($data['id']); ?>')">Delete</a>
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