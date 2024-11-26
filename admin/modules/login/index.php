<?php 
if(isset($_POST['username']) && isset($_POST['password'])){
	$username = trim($_POST['username']);
	$password = $_POST['password'];
	
	// Use prepared statement to prevent SQL injection
	$qry = "SELECT id, username FROM login WHERE username = ? AND password = ?";
	$stmt = $dbobj->prepare($qry);
	$stmt->execute([$username, md5($password)]);
	
	if($stmt->rowCount() > 0){
		$data = $stmt->fetch();
		$_SESSION['logindtl'] = $data;
		header("Location: " . BASEURL . "admin/");
		exit();
	} else {
		$error = "Invalid username or password";
	}
}
?>
<form method="post">
	<?php if(isset($error)): ?>
		<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
	<?php endif; ?>
	<table class="table table-bordered table-striped">
		<tr>
			<th colspan="2" style="text-align: center;">Login Form</th>
		</tr>
		<tr>
			<th>Username</th>
			<td><input type="text" name="username" class="form-control"></td>
		</tr>
		<tr>
			<th>Password</th>
			<td><input type="password" name="password" class="form-control"></td>
		</tr>
		<tr>
			<td colspan="2" style="text-align: center;"><input type="submit" value="submit" class="btn btn-primary"></td>
		</tr>
	</table>
</form>