<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Update user's bank account information
     */
    public function updateBankAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:100',
            'bank_account_number' => 'required|string|max:50|regex:/^[0-9]+$/',
            'bank_account_holder' => 'required|string|max:100',
        ], [
            'bank_name.required' => 'Nama bank wajib diisi',
            'bank_account_number.required' => 'Nomor rekening wajib diisi',
            'bank_account_number.regex' => 'Nomor rekening hanya boleh berisi angka',
            'bank_account_holder.required' => 'Nama pemilik rekening wajib diisi',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        $user->update([
            'bank_name' => $request->bank_name,
            'bank_account_number' => $request->bank_account_number,
            'bank_account_holder' => $request->bank_account_holder,
        ]);

        return response()->json([
            'message' => 'Bank account updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Get user's bank account information (masked)
     */
    public function getBankAccount(Request $request)
    {
        $user = $request->user();

        $bankAccount = null;
        if ($user->bank_account_number) {
            $bankAccount = [
                'bank_name' => $user->bank_name,
                'bank_account_number' => $this->maskAccountNumber($user->bank_account_number),
                'bank_account_number_full' => $user->bank_account_number, // For edit form
                'bank_account_holder' => $user->bank_account_holder,
            ];
        }

        return response()->json([
            'bank_account' => $bankAccount
        ]);
    }

    /**
     * Mask account number for display (show only last 4 digits)
     */
    private function maskAccountNumber($accountNumber)
    {
        if (strlen($accountNumber) <= 4) {
            return $accountNumber;
        }

        $lastFour = substr($accountNumber, -4);
        $masked = str_repeat('*', strlen($accountNumber) - 4) . $lastFour;

        return $masked;
    }
}
