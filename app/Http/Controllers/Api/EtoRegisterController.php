<?php

namespace App\Http\Controllers\Api;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Models\PrivateKey;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Fortify;
use Throwable;

class EtoRegisterController extends Controller
{
    private const SSH_KEY_NAME = 'Etospheres Key';
    private const SSH_KEY_DESCRIPTION = 'This is the common Etpspheres Key';

    public function create_user(Request $request, CreateNewUser $create_new_user)
    {
        $allowedFields = ['name', 'email'];

        $return = validateIncomingRequest($request);

        if ($return instanceof \Illuminate\Http\JsonResponse) {
            return $return;
        }

        $validator = customApiValidator($request->all(), [
            'name' => 'string|max:255',
            'email' => 'string|max:255',
        ]);

        $extraFields = array_diff(array_keys($request->all()), $allowedFields);
        
        if ($validator->fails() || ! empty($extraFields)) {
            $errors = $validator->errors();

            if (! empty($extraFields)) {
                foreach ($extraFields as $field) {
                    $errors->add($field, 'This field is not allowed.');
                }
            }

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }

        try {
            $user = User::create([
                'name'     => $request->get('name'),
                'email'    => $request->get('email'),
                'password' => Hash::make('qwe123'),
                // 'password' => Hash::make(
                //     substr(bin2hex(random_bytes(32)), 0, 32)
                // ),
            ]);
        } catch (Throwable $exception) {
            if ($exception instanceof UniqueConstraintViolationException) {
                return [
                    'error' => 'user already exists'
                ];
            }

            return [
                'error'             => 'error creating user',
                'exception_message' => $exception->getMessage(),
                'exception_class'   => get_class($exception),
                'exception_trace'   => $exception->getTraceAsString(),
            ];
        }

        try {
            $privateKey = PrivateKey::createAndStore([
                'name'        => self::SSH_KEY_NAME,
                'description' => self::SSH_KEY_DESCRIPTION,
                'private_key' => env('ETOSPHERES_SSH_KEY'),
                'team_id'     => $user->teams()->first(),
            ]);
        } catch (Throwable $exception) {
            return [
                'error'             => 'error creating PPK',
                'exception_message' => $exception->getMessage(),
                'exception_class'   => get_class($exception),
                'exception_trace'   => $exception->getTraceAsString(), 
            ];
        }
        
        Password::broker(config('fortify.passwords'))->sendResetLink(
            $request->only(Fortify::email())
        );
        
        //send PW change mail?
            // $user->sendVerificationEmail();
        
        return response()->json([
            $user
        ])->setStatusCode(201);
    }

    // private function generate_invite_link(bool $sendEmail = false)
    // {
    //     try {
    //         // $this->validate();
    //         // if (auth()->user()->role() === 'admin' && $this->role === 'owner') {
    //         //     throw new \Exception('Admins cannot invite owners.');
    //         // }

    //         $member_emails = currentTeam()->members()->get()->pluck('email');

    //         if ($member_emails->contains($this->email)) {
    //             return handleError(livewire: $this, customErrorMessage: "$this->email is already a member of ".currentTeam()->name.'.');
    //         }
    //         $uuid = new Cuid2(32);
    //         $link = url('/').config('constants.invitation.link.base_url').$uuid;
    //         $user = User::whereEmail($this->email)->first();

    //         if (is_null($user)) {
    //             $password = Str::password();
    //             $user = User::create([
    //                 'name' => str($this->email)->before('@'),
    //                 'email' => $this->email,
    //                 'password' => Hash::make($password),
    //                 'force_password_reset' => true,
    //             ]);
    //             $token = Crypt::encryptString("{$user->email}@@@$password");
    //             $link = route('auth.link', ['token' => $token]);
    //         }
    //         $invitation = TeamInvitation::whereEmail($this->email)->first();
    //         if (! is_null($invitation)) {
    //             $invitationValid = $invitation->isValid();
    //             if ($invitationValid) {
    //                 return handleError(livewire: $this, customErrorMessage: "Pending invitation already exists for $this->email.");
    //             } else {
    //                 $invitation->delete();
    //             }
    //         }

    //         $invitation = TeamInvitation::firstOrCreate([
    //             'team_id' => currentTeam()->id,
    //             'uuid' => $uuid,
    //             'email' => $this->email,
    //             'role' => $this->role,
    //             'link' => $link,
    //             'via' => $sendEmail ? 'email' : 'link',
    //         ]);
    //         if ($sendEmail) {
    //             $mail = new MailMessage;
    //             $mail->view('emails.invitation-link', [
    //                 'team' => currentTeam()->name,
    //                 'invitation_link' => $link,
    //             ]);
    //             $mail->subject('You have been invited to '.currentTeam()->name.' on '.config('app.name').'.');
    //             send_user_an_email($mail, $this->email);
    //             $this->dispatch('success', 'Invitation sent via email.');
    //             $this->dispatch('refreshInvitations');

    //             return;
    //         } else {
    //             $this->dispatch('success', 'Invitation link generated.');
    //             $this->dispatch('refreshInvitations');
    //         }
    //     } catch (\Throwable $e) {
    //         $error_message = $e->getMessage();
    //         if ($e->getCode() === '23505') {
    //             $error_message = 'Invitation already sent.';
    //         }

    //         return handleError(error: $e, livewire: $this, customErrorMessage: $error_message);
    //     }
    // }
}
