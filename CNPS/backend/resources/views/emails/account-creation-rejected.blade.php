<!DOCTYPE html>
<html>
<head>
    <title>Demande rejetée - CNPS LODGE</title>
</head>
<body>
    <h2>Bonjour {{ $name }},</h2>
    <p>Nous sommes au regret de vous informer que votre demande de création de compte a été rejetée pour la raison suivante :</p>
    <p style="background: #fee2e2; padding: 15px; border-radius: 8px; color: #991b1b;">
        {{ $reason }}
    </p>
    <p>Pour plus d'informations, veuillez contacter notre administration.</p>
</body>
</html>