<!DOCTYPE html>
<html>
<head>
    <title>Demande de location rejetée</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #ef4444; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .reason-box { background: #fff; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>❌ Demande rejetée</h1>
        </div>
        <div class="content">
            <p>Bonjour <strong>{{ $user->name }}</strong>,</p>
            
            <p>Nous regrettons de vous informer que votre demande de location a été <strong style="color: #ef4444;">REJETÉE</strong> par l'administration.</p>
            
            <div class="reason-box">
                <h3 style="margin-top: 0;">📝 Raison du rejet :</h3>
                <p>{{ $reason }}</p>
            </div>
            
            <p>Détails de votre demande :</p>
            <ul>
                <li><strong>Immeuble :</strong> {{ $rentalRequest->building->name ?? 'N/A' }}</li>
                <li><strong>Appartement :</strong> {{ $rentalRequest->apartment->apartment_number ?? 'N/A' }}</li>
                <li><strong>Date de début :</strong> {{ $rentalRequest->start_date->format('d/m/Y') }}</li>
            </ul>
            
            <p>Pour toute question ou pour une nouvelle demande, n'hésitez pas à contacter notre service client.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ url('/contact') }}" class="button" style="background: #6b7280;">Contacter le support</a>
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