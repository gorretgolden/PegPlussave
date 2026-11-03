@props(['canMake' => false])
<div class="conf-banner">
    <x-icon name="lock" />
    <span><b>Confidential.</b> Only the Treasurer, Chairperson, Accountant and Patron can see this.
        {{ $canMake ? 'Every entry you make is checked by the Chairperson.' : 'You have view access; only the Treasurer makes entries.' }}</span>
</div>
