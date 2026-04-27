<?php
/**
 * Events page — Propose an event CTA + modal.
 *
 * @package DaemsPublic
 */
?>
<!-- TODO: This CTA and modal are only visible to logged-in members.
     Hide/show based on auth state once member authentication is implemented. -->
<?php /* Temporarily hidden — visible to members only in the future. */ ?>
<?php if (false): ?>
<section class="events-cta text-center">
    <div class="container">
        <h2>Want to organise an event?</h2>
        <p>Propose an event and bring the community together.</p>
        <button
            type="button"
            class="btn btn-dark btn-lg px-5 text-uppercase"
            data-bs-toggle="modal"
            data-bs-target="#proposeEventModal"
        >Propose an event</button>
    </div>
</section>
<?php endif; ?>

<!-- Propose an event modal -->
<div class="modal fade propose-modal" id="proposeEventModal" tabindex="-1" aria-labelledby="proposeEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="proposeEventModalLabel">Propose an event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form id="propose-event-form">

                    <div class="mb-3">
                        <label for="pe-title" class="form-label">Event title</label>
                        <input type="text" class="form-control" id="pe-title" placeholder="e.g. Community Workshop Helsinki" required />
                    </div>

                    <div class="mb-3">
                        <label for="pe-type" class="form-label">Event type</label>
                        <select class="form-select" id="pe-type" required>
                            <option value="" selected disabled>Select a type</option>
                            <option value="meetup">Meetup</option>
                            <option value="workshop">Workshop</option>
                            <option value="online">Online session</option>
                            <option value="conference">Conference</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="pe-date" class="form-label">Proposed date</label>
                        <input type="date" class="form-control" id="pe-date" required />
                    </div>

                    <div class="mb-3">
                        <label for="pe-location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="pe-location" placeholder="e.g. Helsinki, Espoo..." />
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="pe-online" />
                            <label class="form-check-label text-muted" for="pe-online">Online event</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="pe-attendance" class="form-label">Expected attendance <span class="text-muted fw-normal">(approx.)</span></label>
                        <input type="number" class="form-control" id="pe-attendance" min="1" placeholder="e.g. 20" />
                    </div>

                    <div class="mb-3">
                        <label for="pe-description" class="form-label">Description <span class="text-muted fw-normal">(required)</span></label>
                        <textarea class="form-control" id="pe-description" rows="4" placeholder="What is the event about? Who is it for?" required></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="pe-support" class="form-label">What support do you need?</label>
                        <textarea class="form-control" id="pe-support" rows="3" placeholder="e.g. Venue, funding, promotion..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 join-submit-btn">Submit proposal</button>

                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            </div>

        </div>
    </div>
</div>
