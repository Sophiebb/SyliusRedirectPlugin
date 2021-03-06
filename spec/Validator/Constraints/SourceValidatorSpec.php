<?php

declare(strict_types=1);

namespace spec\Setono\SyliusRedirectPlugin\Validator\Constraints;

use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Setono\SyliusRedirectPlugin\Model\RedirectInterface;
use Setono\SyliusRedirectPlugin\Repository\RedirectRepositoryInterface;
use Setono\SyliusRedirectPlugin\Validator\Constraints\InfiniteLoop;
use Setono\SyliusRedirectPlugin\Validator\Constraints\Source;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class SourceValidatorSpec extends ObjectBehavior
{
    public function it_is_constraint_valdiator(): void
    {
        $this->shouldImplement(ConstraintValidatorInterface::class);
    }

    public function let(
        RedirectRepositoryInterface $redirectRepository,
        ExecutionContextInterface $context
    ): void {
        $this->beConstructedWith($redirectRepository);
        $this->initialize($context);
    }

    public function it_does_not_validate_other_constraints(
        ExecutionContextInterface $context,
        RedirectInterface $value
    ): void {
        $context->buildViolation(Argument::any())->shouldNotBeCalled();

        $this->shouldThrow(UnexpectedTypeException::class)->during('validate', [$value, new InfiniteLoop()]);
    }

    public function it_does_not_validate_null_values(
        ExecutionContextInterface $context
    ): void {
        $context->buildViolation(Argument::any())->shouldNotBeCalled();

        $this->validate(null, new Source());
    }

    public function it_throws_an_exception_if_the_value_is_no_redirect(
        ExecutionContextInterface $context
    ): void {
        $context->buildViolation(Argument::any())->shouldNotBeCalled();

        $this->shouldThrow(UnexpectedTypeException::class)
            ->during('validate', ['hello', new Source()]);
    }

    public function it_does_not_validate_disabled_redirects(
        ExecutionContextInterface $context,
        RedirectInterface $redirect
    ): void {
        $redirect->getSource()->willReturn('/source');
        $redirect->isEnabled()->willReturn(false);

        $context->buildViolation(Argument::any())->shouldNotBeCalled();

        $this->validate($redirect, new Source());
    }

    public function it_does_not_add_violation_if_there_is_no_other_redirect(
        ExecutionContextInterface $context,
        RedirectInterface $redirect,
        RedirectRepositoryInterface $redirectRepository
    ): void {
        $source = '/dumb-source';
        $redirect->isEnabled()->willReturn(true);
        $redirect->getSource()->willReturn($source);
        $redirect->getChannels()->willReturn(new ArrayCollection());

        $redirectRepository->findOneEnabledBySource($source)
            ->willReturn(null);

        $context->buildViolation(Argument::any())->shouldNotBeCalled();

        $this->validate($redirect, new Source());
    }

    public function it_does_not_add_violation_if_only_other_same_redirect_is_itself(
        ExecutionContextInterface $context,
        RedirectInterface $redirect,
        RedirectRepositoryInterface $redirectRepository
    ): void {
        $source = '/source';
        $redirect->getId()->willReturn(1);
        $redirect->isEnabled()->willReturn(true);
        $redirect->getSource()->willReturn($source);
        $redirect->getChannels()->willReturn(new ArrayCollection());

        $redirectRepository->findOneEnabledBySource($source)
            ->willReturn($redirect);

        $context->buildViolation(Argument::any())->shouldNotBeCalled();

        $this->validate($redirect, new Source());
    }

    public function it_adds_a_violation_if_there_is_another_route_with_the_same_source(
        ExecutionContextInterface $context,
        RedirectInterface $redirect,
        RedirectInterface $conflictingRedirect,
        RedirectRepositoryInterface $redirectRepository,
        ConstraintViolationBuilderInterface $violationBuilder
    ): void {
        $source = '/some-route';
        $redirect->getId()->willReturn(1);
        $redirect->isEnabled()->willReturn(true);
        $redirect->getSource()->willReturn($source);
        $redirect->getChannels()->willReturn(new ArrayCollection());

        $redirectRepository->findOneEnabledBySource($source)
            ->willReturn($conflictingRedirect);

        $conflictingRedirect->getId()->willReturn(2);
        $conflictingRedirect->getChannels()->willReturn(new ArrayCollection());

        $context->buildViolation('There is already a redirection with source "{{ source }}". Redirection ID : {{ conflictingId }}')->willReturn($violationBuilder);
        $violationBuilder->atPath('source')->willReturn($violationBuilder);
        $violationBuilder->setParameter('{{ source }}', '/some-route')->willReturn($violationBuilder);
        $violationBuilder->setParameter('{{ conflictingId }}', '2')->willReturn($violationBuilder);
        $violationBuilder->addViolation()->shouldBeCalled();

        $this->validate($redirect, new Source());
    }
}
