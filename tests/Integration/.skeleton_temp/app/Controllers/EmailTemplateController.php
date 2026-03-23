<?php

namespace App\Controllers;

use App\Dto\Email\EmailTemplateItem;
use App\Dto\Email\EmailTemplateResponse;
use App\Dto\Email\EmailTemplateStoreRequest;
use App\Models\EmailTemplate;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\HttpException;
use Fennec\Core\Validator;

class EmailTemplateController
{
    #[ApiDescription('Lister les templates email', 'Retourne la liste paginee des templates email.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function index(): array
    {
        $limit = (int) ($_GET['limit'] ?? 20);
        $page = (int) ($_GET['page'] ?? 1);
        $offset = ($page - 1) * $limit;

        $data = EmailTemplate::query()
            ->orderBy('name', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [
            'status' => 'ok',
            'data' => array_map(fn ($item) => $item->toArray(), $data),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
            ],
        ];
    }

    #[ApiDescription('Afficher un template email')]
    #[ApiStatus(200, 'Template trouve')]
    #[ApiStatus(404, 'Template non trouve')]
    public function show(string $id): EmailTemplateResponse
    {
        $item = EmailTemplate::findOrFail((int) $id);

        return new EmailTemplateResponse(...$item->toArray());
    }

    #[ApiDescription('Creer un template email')]
    #[ApiStatus(201, 'Template cree')]
    #[ApiStatus(422, 'Erreur de validation')]
    public function store(EmailTemplateStoreRequest $request): array
    {
        Validator::validate($request);

        $template = EmailTemplate::create([
            'name' => $request->name,
            'locale' => $request->locale,
            'subject' => $request->subject,
            'body' => $request->body,
        ]);

        return [
            'status' => 'ok',
            'data' => $template->toArray(),
        ];
    }

    #[ApiDescription('Modifier un template email')]
    #[ApiStatus(200, 'Template modifie')]
    #[ApiStatus(404, 'Template non trouve')]
    #[ApiStatus(422, 'Erreur de validation')]
    public function update(string $id, EmailTemplateStoreRequest $request): array
    {
        Validator::validate($request);

        $template = EmailTemplate::findOrFail((int) $id);

        $template->update([
            'name' => $request->name,
            'locale' => $request->locale,
            'subject' => $request->subject,
            'body' => $request->body,
        ]);

        return [
            'status' => 'ok',
            'data' => $template->toArray(),
        ];
    }

    #[ApiDescription('Supprimer un template email')]
    #[ApiStatus(200, 'Template supprime')]
    #[ApiStatus(404, 'Template non trouve')]
    public function delete(string $id): array
    {
        $template = EmailTemplate::findOrFail((int) $id);
        $template->delete();

        return [
            'status' => 'ok',
            'message' => 'Template email supprime.',
        ];
    }
}