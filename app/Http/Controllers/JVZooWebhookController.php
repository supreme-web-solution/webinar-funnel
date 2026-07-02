<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeMail;
use App\Models\Product;
use App\Models\User;
use App\Services\Jvzoo\JvzooIpnVerifier;
use App\Services\Jvzoo\JvzooUserProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class JVZooWebhookController extends Controller
{
    public function __construct(
        private readonly JvzooIpnVerifier $verifier,
        private readonly JvzooUserProvisioner $provisioner,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifier->verify($request)) {
            return response()->json(['message' => 'Verification Failed!'], 403);
        }

        $data = $request->all();
        $type = isset($data['ctransaction']) ? (string) $data['ctransaction'] : 'SALE';
        $email = isset($data['ccustemail']) ? (string) $data['ccustemail'] : null;
        $transactionId = isset($data['ctransreceipt']) ? (string) $data['ctransreceipt'] : null;
        $productId = isset($data['cproditem']) ? (int) $data['cproditem'] : null;

        if (! $productId || ! $email || ! $transactionId) {
            return response()->json(['message' => 'Item does not exist.'], 422);
        }

        $product = Product::query()->where('product_id', $productId)->first();

        if (! $product) {
            return response()->json(['message' => 'Product not found!'], 404);
        }

        return match ($type) {
            'SALE' => $this->handleSale($email, $product->funnel),
            'RFND' => $this->handleRefund($email),
            default => response()->json(['message' => 'Invalid transaction type!'], 422),
        };
    }

    private function handleSale(string $email, string $roleName): JsonResponse
    {
        $result = $this->provisioner->provision($email, $roleName);

        if ($result['created'] && is_string($result['password'])) {
            Mail::to($email)->send(new WelcomeMail($result['user'], $result['password']));

            $notifyEmail = config('jvzoo.welcome_notify_email');

            if (is_string($notifyEmail) && $notifyEmail !== '') {
                Mail::to($notifyEmail)->send(new WelcomeMail($result['user'], $result['password']));
            }

            return response()->json(['message' => 'User created successfully!']);
        }

        return response()->json(['message' => 'User role updated successfully!']);
    }

    private function handleRefund(string $email): JsonResponse
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return response()->json(['message' => 'User not found!'], 404);
        }

        $this->provisioner->revokeAccess($user);

        return response()->json(['message' => 'User access revoked successfully!']);
    }
}
