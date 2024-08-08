<?php
  # Credits to https://github.com/bootstrapguru/dexor/

  namespace UseTheFork\Synapse\Attributes;

  use Attribute;

  #[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PARAMETER)]
  final class Description
  {
    public function __construct(
      public string $value,
    ) {}
  }
