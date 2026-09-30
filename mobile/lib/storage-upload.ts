/**
 * Client helper for uploading a file to S3 via a presigned PUT URL returned
 * by `trpc.artworks.requestUploadUrl`.
 *
 * Usage:
 *   const { uploadUrl, key } = await requestUploadUrl({ filename, contentType });
 *   await putFileToPresignedUrl(uploadUrl, blob, contentType);
 *   await createArtwork({ fileKey: key, mimeType: contentType, fileSizeBytes, ... });
 */

export async function putFileToPresignedUrl(
  uploadUrl: string,
  body: Blob | ArrayBuffer | Uint8Array,
  contentType: string,
): Promise<void> {
  const resp = await fetch(uploadUrl, {
    method: "PUT",
    headers: { "Content-Type": contentType },
    body: body as BodyInit,
  });
  if (!resp.ok) {
    const msg = await resp.text().catch(() => resp.statusText);
    throw new Error(`Upload failed (${resp.status}): ${msg}`);
  }
}

/**
 * Convert a local file URI (e.g. from expo-document-picker / expo-image-picker)
 * into a Blob the upload helper can send. Works on web + native via fetch().
 */
export async function fileUriToBlob(uri: string): Promise<Blob> {
  const resp = await fetch(uri);
  if (!resp.ok) {
    throw new Error(`Failed to read local file (${resp.status})`);
  }
  return resp.blob();
}
