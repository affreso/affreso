<div class="grid-2">
  <label><?= e(__('Title')) ?><input name="title" required value="<?= e($l['title'] ?? '') ?>"></label>
  <label><?= e(__('Type')) ?><select name="lesson_type" data-lesson-type><?= options($types, $l['lesson_type'] ?? 'text') ?></select></label>
</div>
<label data-show-for="video"><?= e(__('Video link')) ?><input type="url" name="video_url" value="<?= e($l['video_url'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=…"></label>
<label data-show-for="pdf"><?= e(__('Upload file (PDF, Word, PowerPoint)')) ?><input type="file" name="file" accept=".pdf,.docx,.pptx,.png,.jpg,.jpeg"></label>
<label><?= e(__('Content')) ?><textarea name="body" rows="6" placeholder="<?= e(__('Lesson text, or the Bible passage with its reference')) ?>"><?= e($l['body'] ?? '') ?></textarea></label>
<label><?= e(__('Order')) ?><input type="number" name="sort" value="<?= (int)($l['sort'] ?? 0) ?>" style="max-width:100px"></label>
