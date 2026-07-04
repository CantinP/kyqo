<?php

namespace Kyqo\Mail;

/**
 * Mailable — base class for all mail objects.
 *
 * Extend this class and implement build() to define the message.
 *
 * Usage:
 *   class WelcomeMail extends Mailable
 *   {
 *       public function __construct(private User $user) {}
 *
 *       public function build(): static
 *       {
 *           return $this->subject('Welcome to Kyqo!')
 *                       ->view('emails.welcome', ['user' => $this->user]);
 *       }
 *   }
 *
 *   Mail::send(new WelcomeMail($user));
 *
 *   // Or with the fluent helper:
 *   Mail::to('user@example.com')->send(new WelcomeMail($user));
 */
abstract class Mailable
{
    protected ?string $viewName     = null;
    protected array   $viewData     = [];
    protected ?string $htmlContent  = null;
    protected ?string $textContent  = null;
    protected ?string $subjectLine  = null;
    protected array   $toList       = [];
    protected array   $ccList       = [];
    protected array   $bccList      = [];
    protected ?string $fromAddress  = null;
    protected ?string $fromName     = null;
    protected array   $attachments  = [];
    protected array   $rawHeaders   = [];

    /**
     * Define the message. Must be implemented by every mailable.
     */
    abstract public function build(): static;

    // ---- Builder helpers (called inside build()) ----------------------------

    public function subject(string $subject): static
    {
        $this->subjectLine = $subject;
        return $this;
    }

    public function view(string $view, array $data = []): static
    {
        $this->viewName = $view;
        $this->viewData = $data;
        return $this;
    }

    public function html(string $html): static
    {
        $this->htmlContent = $html;
        return $this;
    }

    public function text(string $text): static
    {
        $this->textContent = $text;
        return $this;
    }

    public function to(string $address, string $name = ''): static
    {
        $this->toList[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function cc(string $address, string $name = ''): static
    {
        $this->ccList[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function bcc(string $address, string $name = ''): static
    {
        $this->bccList[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function from(string $address, string $name = ''): static
    {
        $this->fromAddress = $address;
        $this->fromName    = $name;
        return $this;
    }

    public function attach(string $path, string $name = '', string $mime = ''): static
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name ?: basename($path),
            'mime' => $mime ?: 'application/octet-stream',
        ];
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->rawHeaders[$name] = $value;
        return $this;
    }

    // ---- Internals ----------------------------------------------------------

    /**
     * Build and return a Message instance ready to be sent.
     * Called internally by MailManager::send().
     */
    public function toMessage(?\Kyqo\View\Engine $view = null): Message
    {
        $this->build();

        $msg = new Message();

        foreach ($this->toList  as $r) { $msg->to($r['address'], $r['name']); }
        foreach ($this->ccList  as $r) { $msg->cc($r['address'], $r['name']); }
        foreach ($this->bccList as $r) { $msg->bcc($r['address'], $r['name']); }

        if ($this->fromAddress) {
            $msg->from($this->fromAddress, $this->fromName ?? '');
        }

        if ($this->subjectLine) {
            $msg->subject($this->subjectLine);
        }

        // Render view if provided
        if ($this->viewName !== null && $view !== null) {
            $msg->html($view->render($this->viewName, $this->viewData));
        } elseif ($this->htmlContent !== null) {
            $msg->html($this->htmlContent);
        }

        if ($this->textContent !== null) {
            $msg->text($this->textContent);
        }

        foreach ($this->attachments as $att) {
            $msg->attach($att['path'], $att['name'], $att['mime']);
        }

        foreach ($this->rawHeaders as $name => $value) {
            $msg->header($name, $value);
        }

        return $msg;
    }
}
