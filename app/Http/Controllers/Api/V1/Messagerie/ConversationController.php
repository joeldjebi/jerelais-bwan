<?php

namespace App\Http\Controllers\Api\V1\Messagerie;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\Support\RelaisSender;
use App\Domain\Messagerie\Actions\SendMessage;
use App\Domain\Messagerie\Models\Conversation;
use App\Domain\Messagerie\Models\Message;
use App\Domain\Relais\Models\RelaisProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Messagerie du Relais avec l'équipe de sa commune (MOB-22).
 */
class ConversationController extends Controller
{
    /**
     * Mes conversations, la plus récente d'abord.
     */
    public function index(Request $request): JsonResponse
    {
        $profile = $this->profile($request);

        $conversations = Conversation::withoutCommuneRestriction()
            ->with(['relaisItem:id,uuid,title', 'messages' => fn ($query) => $query->reorder()->latest('id')->limit(1)])
            ->where('relais_profile_id', $profile->id)
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json(['data' => $conversations->map(fn (Conversation $conversation): array => $this->present($conversation))]);
    }

    /**
     * Écrire à l'équipe : conversation générale, ou rattachée à un de mes relais (`relais`).
     */
    public function store(Request $request, SendMessage $send): JsonResponse
    {
        $profile = $this->profile($request);
        $data = $request->validate(['relais' => ['nullable', 'uuid'], 'body' => ['required', 'string', 'max:2000']]);
        $relais = isset($data['relais']) ? RelaisItem::withoutCommuneRestriction()->where('uuid', $data['relais'])->where('user_id', $profile->user_id)->first() : null;

        if (isset($data['relais']) && $relais === null) {
            throw ValidationException::withMessages(['relais' => __('conversations.errors.not_owner')]);
        }

        $conversation = $send->conversationFor($profile, $relais);
        $send->send($conversation, $this->user($request), $data['body']);

        return response()->json(['data' => $this->present($conversation->load(['relaisItem', 'messages']))], 201);
    }

    /**
     * Messages d'une conversation (marqués comme lus).
     */
    public function messages(Request $request, Conversation $conversation, SendMessage $send): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);
        $send->markRead($conversation, $this->user($request));

        return response()->json(['data' => $conversation->messages()->with('sender:id,name')->get()->map(fn (Message $message): array => [
            'uuid' => $message->uuid,
            'from_staff' => $message->from_staff,
            'author' => $message->from_staff ? __('Votre coordonnateur') : __('Vous'),
            'body' => $message->body,
            'sent_at' => $message->created_at->toIso8601ZuluString(),
        ])]);
    }

    /**
     * Répondre dans une conversation.
     */
    public function reply(Request $request, Conversation $conversation, SendMessage $send): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        try {
            $message = $send->send($conversation, $this->user($request), $data['body']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['body' => $exception->getMessage()]);
        }

        return response()->json(['data' => ['uuid' => $message->uuid, 'sent_at' => $message->created_at->toIso8601ZuluString()]], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Conversation $conversation): array
    {
        $last = $conversation->messages->sortByDesc('id')->first();

        return [
            'uuid' => $conversation->uuid,
            'relais' => $conversation->relaisItem === null ? null : ['uuid' => $conversation->relaisItem->uuid, 'title' => $conversation->relaisItem->title],
            'last_message' => $last === null ? null : ['body' => $last->body, 'from_staff' => $last->from_staff, 'sent_at' => $last->created_at->toIso8601ZuluString()],
            'unread' => $conversation->last_message_at !== null && $last?->from_staff === true
                && ($conversation->relais_read_at === null || $conversation->relais_read_at->lt($conversation->last_message_at)),
        ];
    }

    private function authorizeOwner(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->relais_profile_id === $this->profile($request)->id, 404);
    }

    private function profile(Request $request): RelaisProfile
    {
        $profile = RelaisSender::profileOf($this->user($request));
        abort_if($profile === null, 403);

        return $profile;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
