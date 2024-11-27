<?php

	$aboutData = $dbobj->fetchOne('about',1);
	if($aboutData){
		extract($aboutData);
	}
	
if(isset($_POST['description']) && !empty($_POST['description'])){
	$description = trim($_POST['description']);
	$postData = ['description' => $description];
	
	$dbobj->addEdit('about', $postData, 1);
	$_SESSION['message'] = "About section updated successfully";
	header("Location:".BASEURL."admin/about");
	exit;
}

?>
<script src="https://cdn.ckeditor.com/ckeditor5/12.4.0/classic/ckeditor.js"></script>
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


<?php if(isset($_SESSION['message'])): ?>
	<div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message']); ?></div>
	<?php unset($_SESSION['message']); ?>
<?php endif; ?>

<form method="post" action="">
	<h1>About</h1>
	<div class="main">
		<textarea name="description" id="editor"><?php echo htmlspecialchars(isset($description) ? $description : '');?></textarea>
		<div class="one">
			<button type="submit">save</button>
	 	</div>
	 </div>
</form>
<script>
    ClassicEditor
        .create( document.querySelector( '#editor' ) )
        .catch( error => {
            console.error( error );
        } );
</script>