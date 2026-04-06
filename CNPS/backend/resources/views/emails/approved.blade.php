<!DOCTYPE html>
<html>
<head>
    <title>Bienvenue chez CNPS ARENA</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #2563eb, #1e40af); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .credentials { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; border: 1px solid #e5e7eb; }
        .button { display: inline-block; background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; margin-top: 20px; }
        .warning { background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bienvenue chez CNPS ARENA</h1>
            <p>Votre compte a été approuvé !</p>
        </div>
        <div class="content">
            <p>Bonjour <strong>{{ $user->name }}</strong>,</p>
            <p>Nous avons le plaisir de vous informer que votre demande d'inscription a été <strong style="color: #22c55e;">APPROUVÉE</strong>.</p>
            
            <div class="credentials">
                <h3>Vos identifiants de connexion :</h3>
                <p><strong>Email :</strong> {{ $user->email }}</p>
                <p><strong>Mot de passe temporaire :</strong> <code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px;">{{ $password }}</code></p>
            </div>

            <div class="warning">
                <strong>⚠️ Important :</strong>
                <p style="margin: 10px 0 0 0;">Pour des raisons de sécurité, nous vous recommandons de changer votre mot de passe immédiatement après votre première connexion.</p>
            </div>

            <p>Vous pouvez dès maintenant :</p>
            <ul>
                <li>Consulter les immeubles disponibles</li>
                <li>Compléter votre profil</li>
                <li>Soumettre des demandes de location</li>
                <li>Suivre vos paiements</li>
            </ul>

            <a href="{{ url('/login/user') }}" class="button">Se connecter</a>
            
            <p style="margin-top: 30px; font-size: 14px; color: #6b7280;">
                Cordialement,<br>
                <strong>L'équipe CNPS ARENA</strong>
            </p>
        </div>
    </div>
</body>
</html>