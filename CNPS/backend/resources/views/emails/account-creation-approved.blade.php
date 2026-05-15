<!DOCTYPE html>
<html>
<head>
    <title>Compte créé - CNPS LODGE</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
        .button { display: inline-block; padding: 12px 24px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
        .credentials { background: #f0fdf4; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #22c55e; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CNPS LODGE</h1>
            <p>Votre compte a été créé avec succès</p>
        </div>
        <div class="content">
            <h2>Bonjour {{ $name }},</h2>
            <p>Nous avons le plaisir de vous informer que votre demande de création de compte a été approuvée.</p>
            
            <div class="credentials">
                <h3>Vos identifiants de connexion :</h3>
                <p><strong>Email :</strong> {{ $email }}</p>
                <p><strong>Mot de passe temporaire :</strong> {{ $password }}</p>
                <p style="margin-top: 10px; font-size: 12px; color: #666;">Veuillez changer votre mot de passe après votre première connexion.</p>
            </div>
            
            <a href="{{ $loginUrl }}" class="button">Se connecter</a>
            
            <p style="margin-top: 20px;">Après votre première connexion, vous pourrez soumettre votre demande de location.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} CNPS LODGE. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>