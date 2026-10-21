export interface FormSubmissionPayload {
  form_type: string;
  form_title: string;
  user_name?: string;
  user_phone?: string;
  user_email?: string;
  score_summary?: string;
  answers: Record<string, any>;
}

/**
 * Sends form submission payload directly to the EduTarbiyah central WordPress database
 */
export async function submitFormToBackend(
  payload: FormSubmissionPayload
): Promise<{ success: boolean; submission_id?: number; error?: string }> {
  try {
    const res = await fetch('/wp-json/edutarbiyah/v1/submit-form', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      throw new Error(`Server returned HTTP ${res.status}`);
    }

    const data = await res.json();
    return {
      success: !!data.success,
      submission_id: data.submission_id,
    };
  } catch (err: any) {
    console.warn('[EduTarbiyah Submissions] Failed to save to server:', err?.message || err);
    return {
      success: false,
      error: err?.message || 'Network error',
    };
  }
}
