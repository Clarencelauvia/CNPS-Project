<!DOCTYPE html>
<html>
<head>
    <title>Demande de location approuvée</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2563eb; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Demande approuvée !</h1>
        </div>
        <div class="content">
            <p>Bonjour <strong>{{ $user->name }}</strong>,</p>
            
            <p>Nous avons le plaisir de vous informer que votre demande de location a été <strong style="color: #22c55e;">APPROUVÉE</strong> par l'administration.</p>
            
            <div style="background: #fff; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <h3 style="margin-top: 0;">📋 Détails de votre demande :</h3>
                <p><strong>Immeuble :</strong> {{ $rentalRequest->building->name ?? 'N/A' }}</p>
                <p><strong>Appartement :</strong> {{ $rentalRequest->apartment->apartment_number ?? 'N/A' }}</p>
                <p><strong>Date de début :</strong> {{ $rentalRequest->start_date->format('d/m/Y') }}</p>
                <p><strong>Durée :</strong> {{ $rentalRequest->duration }} mois</p>
            </div>
            
            <p>Vous pouvez maintenant procéder à la signature de votre contrat de location. Un agent vous contactera dans les plus brefs délais pour finaliser les démarches.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ url('/dashboard/user') }}" class="button">Accéder à mon espace</a>
            </div>
            
            <p>Cordialement,<br><strong>L'équipe CNPS LODGE</strong></p>
        </div>
        <div class="footer">
            <p>CNPS LODGE - Votre partenaire immobilier de confiance</p>
            <p>© {{ date('Y') }} CNPS LODGE. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>