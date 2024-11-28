<?php
// Display success/error messages
if(isset($_SESSION['message'])){
	echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['message']) . '</div>';
	unset($_SESSION['message']);
}

if($id && is_numeric($id)){
	$dbobj->delete('team',$id);
	$_SESSION['message']="Team member deleted successfully";
	header("Location:".BASEURL."admin/team");
	exit;
}
	$alldata=$dbobj->fetchAll("select * from team order by id desc");
	
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
		<th>Description</th>
		<th>photos</th>
		<th>Action</th>
	</tr>
	<tr>
		<td colspan="5"><a href="<?php echo BASEURL;?>admin/team/create">Add New Record</a></td>
	</tr>
	<?php
	$sno=0;
	foreach ($alldata as $data) {
	?>
	<tr>
		<td><?php echo ++$sno; ?></td>
		<td><?php echo $data['name'] ?></td>
		<td><?php echo $data['title'] ?></td>
		<td>
			<?php if($data['photo']){
							if(file_exists("../public/images/$data[photo]")){
							?>
								<img class="one" src="<?php echo BASEURL."public/images/$data[photo]";?>" height="50px" width="50px" />
							<?php

							}else{
								echo "Image not found";
							}

						}else{
							echo "No image uploaded";
						}				
			?>
		</td>
		<td>
			<a href="<?php echo BASEURL;?>admin/team/create/<?php echo htmlspecialchars($data['id']); ?>">Edit</a>&nbsp; &nbsp; | &nbsp; &nbsp;
			<a href="#" onclick="delclick('<?php echo BASEURL;?>admin/team/index/<?php echo htmlspecialchars($data['id']); ?>')">Delete</a>
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