<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UniqueUserPseudoValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueUserPseudo) {
            throw new UnexpectedTypeException($constraint, UniqueUserPseudo::class);
        }

        if (!$value instanceof User || null === $value->getId()) {
            return;
        }

        $pseudo = $value->getPseudo();
        if (null === $pseudo || '' === trim($pseudo)) {
            return;
        }

        $existing = $this->userRepository->findOneByPseudoForAnotherUser($pseudo, $value->getId());

        if (null !== $existing) {
            $this->context->buildViolation($constraint->message)
                ->atPath('pseudo')
                ->addViolation();
        }
    }
}
