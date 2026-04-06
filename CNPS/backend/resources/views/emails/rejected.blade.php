<!DOCTYPE html>
<html>
<head>
    <title>Demande d'inscription - CNPS ARENA</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #dc2626, #991b1b); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .reason { background: #fee2e2; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #dc2626; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Demande d'inscription</h1>
            <p>Mise à jour du statut</p>
        </div>
        <div class="content">
            <p>Bonjour <strong>{{ $user->name }}</strong>,</p>
            <p>Nous avons examiné votre demande d'inscription. Malheureusement, elle n'a pas été <strong style="color: #dc2626;">APPROUVÉE</strong> pour le moment.</p>
            
            <div class="reason">
                <strong>📝 Raison du rejet :</strong>
                <p style="margin-top: 10px;">{{ $reason }}</p>
            </div>

            <p>Nous vous invitons à :</p>
            <ul>
                <li>Corriger les informations fournies</li>
                <li>Compléter les documents manquants</li>
                <li>Nous contacter pour plus d'informations</li>
            </ul>

            <p>Vous pouvez soumettre une nouvelle demande après avoir apporté les corrections nécessaires.</p>
            
            <p style="margin-top: 30px; font-size: 14px; color: #6b7280;">
                Cordialement,<br>
                <strong>L'équipe CNPS ARENA</strong>
            </p>
        </div>
    </div>
</body>
</html>