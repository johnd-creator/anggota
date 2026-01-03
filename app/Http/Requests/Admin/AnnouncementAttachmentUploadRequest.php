<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AnnouncementAttachmentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controlled by Gate in Controller
    }

    public function rules(): array
    {
        return [
            'attachments' => ['required', 'array', 'max:5', 'min:1'],
            'attachments.*' => [
                'file',
                'max:5120', // 5MB
                // Allow common docs and images
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,ppt,pptx,txt',
            ],
        ];
    }

    public function messages()
    {
        return [
            'attachments.max' => 'Maksimal 5 file dapat diupload sekaligus.',
            'attachments.*.max' => 'Ukuran file tidak boleh lebih dari 5MB.',
            'attachments.*.mimes' => 'Format file harus berupa PDF, Gambar, Office Docs (Word/Excel/PPT), atau TXT.',
        ];
    }
}
