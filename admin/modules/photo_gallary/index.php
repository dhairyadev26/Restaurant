<?php
// Display success/error messages
if(isset($_SESSION['message'])){
	echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['message']) . '</div>';
	unset($_SESSION['message']);
}

if($id && is_numeric($id)){
	$dbobj->delete('photo_gallary',$id);
	$_SESSION['message'] = "Photo deleted successfully";
	header("Location:".BASEURL."admin/photo_gallary");
	exit;
}
	$alldata=$dbobj->fetchAll("select * from photo_gallary order by id desc");


?>
<style type="text/css">
	table{
		font-size: 20px;
	}
</style>


<table class="table table-bordered table-striped table-hover">
	<tr>
		<th>S.No.</th>
		<th>Title</th>
		<th>Group</th>
		<th>Date</th>
		<th>Images</th>
		<th>Action</th>
	</tr>
	<tr>
		<td colspan="6"><a href="<?php echo BASEURL;?>admin/photo_gallary/create">Add New Record</a></td>
	</tr>
	<?php
	$sno=0;
	foreach ($alldata as $data) {
	?>
	<tr>
		<td><?php echo ++$sno; ?></td>
		<td><?php echo $data['title'] ?></td>
		<td><?php echo $data['groupp'] ?></td>
		<td><?php echo $data['datee'] ?></td>
		<td>
			<?php if($data['image']){
							if(file_exists("../public/images/$data[image]")){
							?>
								<img class="one" src="<?php echo BASEURL."public/images/$data[image]";?>" height="50px" width="50px" />
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
			<a href="#" onclick="delclick('<?php echo BASEURL;?>admin/photo_gallary/index/<?php echo htmlspecialchars($data['id']); ?>')">Delete</a>
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