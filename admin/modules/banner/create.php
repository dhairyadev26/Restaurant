<?php
if($id && is_numeric($id)){
	$data = $dbobj->fetchOne('banner',$id);
	if($data){
		extract($data);
	}
}

if(isset($_POST['title']) && !empty($_POST['title'])){
	$title = trim($_POST['title']);
	$description = trim($_POST['description']);
	
	$postData = [
		'title' => $title,
		'description' => $description
	];
	
	if($_FILES['image']['name'] && $_FILES['image']['error'] == 0){
		$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
		if(in_array($_FILES['image']['type'], $allowedTypes)){
			$imageName = time()."_image_".$_FILES['image']['name'];
			$uploadPath = "../public/images/".$imageName;
			
			if(move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)){
				$postData['image'] = $imageName;
			}
		}
	}
	
	$dbobj->addEdit('banner', $postData, $id);
	$_SESSION['message'] = $id ? "Banner updated successfully" : "Banner added successfully";
	header("Location:".BASEURL."admin/banner");
	exit;
}

?>
<link rel="stylesheet" type="text/css" href="../public/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../public/css/style.css">
<link href="../public/css/font-awesome.min.css" rel="stylesheet">

<style type="text/css">
	form{
		padding: 20px;
		background-color:#fff;
		/*border: 2px solid;*/
		width: 60%;
		margin: 0 auto;

		box-shadow: 5px 5px 15px;
	}
	form h1{
		text-align: center;
		color: #83FFE3;
		margin: 0;
	}
	div.main{
		padding: 5px;

	}
	form div.one{
		padding: 12px;
		/*border: 1px solid;*/
		margin: 0 auto;
		width: 100%;
	

	}
	form div.one button{
		margin: 0 auto;
	}
	div.one input{
		margin: 10px;
	}
	div.one textarea{
		margin-left: 10px;
		height: 45px;
		width: 150px;
	}
</style>


<form method="post" action="" enctype="multipart/form-data">
	<h1>BANNER</h1>
	<div class="main">
		<div class="one">
			<?php if(isset($image) && $image){ ?>
						Uploaded Image:
					<?php
					if(file_exists("../public/images/$image")){
					?>
						<img src="<?php echo BASEURL."public/images/$image";?>" height="50px" width="50px" />
					<?php
					}else{
						echo "Image not found";
					}
				}
			?>
		</div>
		<div class="one">
			Image:<input type="file" name="image"><br/>
		</div>
		<div class="one">
			Title:<input type="text" name="title" value="<?php echo htmlspecialchars(isset($title) ? $title : '');?>" required><br/>
		</div>
		<div class="one">
			Description:<textarea name="description" rows="3" style="width: 100%;"><?php echo htmlspecialchars(isset($description) ? $description : '');?></textarea><br/>
		</div>
		<div class="one">
			<button type="submit">save</button>
	 	</div>
 	</div>
</form>